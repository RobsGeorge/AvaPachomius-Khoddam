<?php

namespace App\Services;

use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use App\Models\RegistrationReviewTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationReviewService
{
    public function __construct(
        private RegistrationApplicationService $applications,
        private CourseRoleAssignmentService $roleAssignment,
        private RegistrationReviewMailService $mail
    ) {}

    /** @param array<string, array{status?: string, comment?: string|null}> $fieldInput */
    public function saveFieldReviews(RegistrationApplication $application, array $fieldInput): void
    {
        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $fieldKey) {
            $input = $fieldInput[$fieldKey] ?? [];
            $status = ($input['status'] ?? RegistrationApplicationFieldReview::STATUS_ACCEPTED) === RegistrationApplicationFieldReview::STATUS_REJECTED
                ? RegistrationApplicationFieldReview::STATUS_REJECTED
                : RegistrationApplicationFieldReview::STATUS_ACCEPTED;

            RegistrationApplicationFieldReview::updateOrCreate(
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

    public function requestCorrections(RegistrationApplication $application, User $admin, array $fieldInput): RegistrationApplication
    {
        $this->saveFieldReviews($application, $fieldInput);

        $application->load('fieldReviews');

        if ($application->rejectedFields()->count() === 0) {
            throw ValidationException::withMessages([
                'fields' => __('registration_review.at_least_one_rejected_field'),
            ]);
        }

        return DB::transaction(function () use ($application, $admin) {
            $application->update([
                'status' => RegistrationApplication::STATUS_NEEDS_CORRECTION,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => null,
            ]);

            $user = $application->user;
            $this->applications->syncUserApplicationStatus($user, RegistrationApplication::STATUS_NEEDS_CORRECTION);

            $this->mail->send($user, RegistrationReviewTemplate::KEY_NEEDS_CORRECTION, [
                'fields_table' => $this->mail->buildFieldsTable($application->fresh('fieldReviews')),
            ]);

            return $application->fresh(['fieldReviews', 'user']);
        });
    }

  /** @param list<array{course_id: int, role_id: int}> $roleAssignments */
    public function approve(
        RegistrationApplication $application,
        User $admin,
        array $fieldInput,
        array $roleAssignments,
        bool $allowRejectedFields = false
    ): RegistrationApplication {
        $this->saveFieldReviews($application, $fieldInput);
        $application->load('fieldReviews');

        if (! $allowRejectedFields && $application->rejectedFields()->count() > 0) {
            throw ValidationException::withMessages([
                'fields' => __('registration_review.cannot_approve_with_rejected_fields'),
            ]);
        }

        if ($roleAssignments === []) {
            throw ValidationException::withMessages([
                'roles' => __('registration_review.role_assignment_required'),
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $roleAssignments) {
            $user = $application->user;

            $this->roleAssignment->assignMany($user, $roleAssignments);

            $application->update([
                'status' => RegistrationApplication::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => null,
            ]);

            $user->forceFill([
                'is_verified' => true,
                'application_status' => RegistrationApplication::STATUS_APPROVED,
            ])->save();

            $this->mail->send($user, RegistrationReviewTemplate::KEY_APPROVED);

            return $application->fresh(['fieldReviews', 'user']);
        });
    }

    public function rejectApplication(RegistrationApplication $application, User $admin, string $note): RegistrationApplication
    {
        return DB::transaction(function () use ($application, $admin, $note) {
            $application->update([
                'status' => RegistrationApplication::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->user_id,
                'overall_rejection_note' => $note,
            ]);

            $user = $application->user;
            $user->forceFill([
                'is_verified' => false,
                'application_status' => RegistrationApplication::STATUS_REJECTED,
            ])->save();

            $this->mail->send($user, RegistrationReviewTemplate::KEY_REJECTED, [
                'note' => $note,
            ]);

            return $application->fresh(['user']);
        });
    }

    public function restore(RegistrationApplication $application, User $admin, string $targetStatus = RegistrationApplication::STATUS_PENDING_REVIEW): RegistrationApplication
    {
        if (! in_array($targetStatus, [
            RegistrationApplication::STATUS_PENDING_REVIEW,
            RegistrationApplication::STATUS_NEEDS_CORRECTION,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => __('registration_review.invalid_restore_status'),
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
            $user->forceFill([
                'is_verified' => false,
                'application_status' => $targetStatus,
            ])->save();

            return $application->fresh(['fieldReviews', 'user']);
        });
    }

    public function resubmit(User $user, array $validated, ?string $profilePhotoPath = null): RegistrationApplication
    {
        $payload = [
            'first_name' => $validated['first_name'],
            'second_name' => $validated['second_name'],
            'third_name' => $validated['third_name'],
            'national_id' => $validated['national_id'],
            'mobile_number' => $validated['mobile_number'],
            'email' => $validated['email'],
            'job' => $validated['job'],
            'date_of_birth' => $validated['date_of_birth'],
        ];

        if ($profilePhotoPath !== null) {
            $payload['profile_photo'] = $profilePhotoPath;
            $payload['profile_photo_uploaded_at'] = now(config('attendance.timezone', config('app.timezone')));
            $payload['profile_photo_status'] = User::PHOTO_STATUS_PENDING;
            $payload['profile_photo_reviewed_at'] = null;
            $payload['profile_photo_reviewed_by_user_id'] = null;
            $payload['profile_photo_rejection_note'] = null;
        }

        $user->forceFill($payload)->save();

        $application = $this->applications->createFromUser($user);
        $this->applications->syncUserApplicationStatus($user, RegistrationApplication::STATUS_PENDING_REVIEW);
        $user->forceFill(['is_verified' => false])->save();

        return $application;
    }
}
