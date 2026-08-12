<?php

namespace App\Services;

use App\Models\CourseApplication;
use App\Models\CourseApplicationFieldReview;
use App\Models\CourseApplicationReviewTemplate;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseApplicationReviewService
{
    public function __construct(
        private CourseApplicationService $applications,
        private CourseRoleAssignmentService $roleAssignment,
        private CourseApplicationMailService $mail,
        private ServiceRoleAssignmentService $serviceMembership,
        private RegistrationApplicationService $registrationApplications,
    ) {}

    /** @param array<string, array{status?: string, comment?: string|null}> $fieldInput */
    public function saveFieldReviews(CourseApplication $application, array $fieldInput): void
    {
        foreach ($application->reviewableFieldKeys() as $fieldKey) {
            $input = $fieldInput[$fieldKey] ?? [];
            $status = ($input['status'] ?? CourseApplicationFieldReview::STATUS_ACCEPTED) === CourseApplicationFieldReview::STATUS_REJECTED
                ? CourseApplicationFieldReview::STATUS_REJECTED
                : CourseApplicationFieldReview::STATUS_ACCEPTED;

            CourseApplicationFieldReview::updateOrCreate(
                [
                    'application_id' => $application->id,
                    'field_key' => $fieldKey,
                ],
                [
                    'status' => $status,
                    'comment' => $input['comment'] ?? null,
                ]
            );
        }
    }

    public function requestCorrections(CourseApplication $application, User $admin, array $fieldInput): CourseApplication
    {
        $this->saveFieldReviews($application, $fieldInput);
        $application->load('fieldReviews');

        if ($application->rejectedFields()->count() === 0) {
            throw ValidationException::withMessages([
                'fields' => __('course_applications.at_least_one_rejected_field'),
            ]);
        }

        return DB::transaction(function () use ($application, $admin) {
            $application->update([
                'status' => CourseApplication::STATUS_NEEDS_CORRECTION,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => null,
            ]);

            $user = $application->user;
            $this->applications->syncCourseApplicationStatus(
                $user,
                $application->course_id,
                CourseApplication::STATUS_NEEDS_CORRECTION
            );

            $this->registrationApplications->syncPlatformStatusFromCourseApplication($user, $application);

            $this->mail->send($user, CourseApplicationReviewTemplate::KEY_NEEDS_CORRECTION, $application, [
                'fields_table' => $this->mail->buildFieldsTable($application->fresh('fieldReviews')),
            ]);

            return $application->fresh(['fieldReviews', 'user', 'course', 'form']);
        });
    }

    public function approve(
        CourseApplication $application,
        User $admin,
        array $fieldInput,
        bool $allowRejectedFields = false,
    ): CourseApplication {
        $this->saveFieldReviews($application, $fieldInput);
        $application->load(['fieldReviews', 'form']);

        if (! $allowRejectedFields && $application->rejectedFields()->count() > 0) {
            throw ValidationException::withMessages([
                'fields' => __('course_applications.cannot_approve_with_rejected_fields'),
            ]);
        }

        $roleId = $application->form?->default_role_id;
        if (! $roleId) {
            throw ValidationException::withMessages([
                'role' => __('course_applications.default_role_required'),
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $roleId) {
            $user = $application->user;

            $exists = UserCourseRole::query()
                ->where('user_id', $user->user_id)
                ->where('course_id', $application->course_id)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                // Admission to a course implies membership in its parent service.
                $this->serviceMembership->ensureMembershipForCourse($user, $application->course_id);
                $this->roleAssignment->assign($user, $application->course_id, $roleId, false);
            }

            $application->update([
                'status' => CourseApplication::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => null,
            ]);

            $this->applications->syncCourseApplicationStatus(
                $user,
                $application->course_id,
                CourseApplication::STATUS_APPROVED
            );

            $this->registrationApplications->syncPlatformStatusFromCourseApplication($user, $application);

            $this->mail->send($user, CourseApplicationReviewTemplate::KEY_APPROVED, $application);

            return $application->fresh(['fieldReviews', 'user', 'course', 'form']);
        });
    }

    public function rejectApplication(CourseApplication $application, User $admin, string $note): CourseApplication
    {
        return DB::transaction(function () use ($application, $admin, $note) {
            $application->update([
                'status' => CourseApplication::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => $note,
            ]);

            $user = $application->user;
            $this->applications->syncCourseApplicationStatus(
                $user,
                $application->course_id,
                CourseApplication::STATUS_REJECTED
            );

            $this->registrationApplications->syncPlatformStatusFromCourseApplication($user, $application);

            $this->mail->send($user, CourseApplicationReviewTemplate::KEY_REJECTED, $application, [
                'note' => $note,
            ]);

            return $application->fresh(['user', 'course']);
        });
    }

    public function restore(
        CourseApplication $application,
        User $admin,
        string $targetStatus = CourseApplication::STATUS_PENDING_REVIEW,
    ): CourseApplication {
        if (! in_array($targetStatus, [
            CourseApplication::STATUS_PENDING_REVIEW,
            CourseApplication::STATUS_NEEDS_CORRECTION,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('course_applications.invalid_restore_status'),
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $targetStatus) {
            $application->update([
                'status' => $targetStatus,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => null,
            ]);

            $application->fieldReviews()->delete();

            $user = $application->user;
            $this->applications->syncCourseApplicationStatus(
                $user,
                $application->course_id,
                $targetStatus
            );

            $this->registrationApplications->syncPlatformStatusFromCourseApplication($user, $application);

            return $application->fresh(['fieldReviews', 'user', 'course']);
        });
    }

    public function resubmit(User $user, CourseApplication $application, array $snapshot): CourseApplication
    {
        $newApplication = $this->applications->createFromSubmission(
            $user,
            $application->form,
            $snapshot
        );

        return $newApplication;
    }
}
