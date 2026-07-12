<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\CourseApplicationForm;
use App\Models\CourseUserApplicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class CourseApplicationService
{
    public function __construct(
        private NotificationGeneratorService $notifications,
        private StudentRosterService $roster,
        private CourseApplicationMailService $mail,
    ) {}

    public function latestForUserCourse(User $user, Course|int $course): ?CourseApplication
    {
        $courseId = $course instanceof Course ? $course->course_id : $course;

        return CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $courseId)
            ->latest('id')
            ->first();
    }

    public function createFromSubmission(
        User $user,
        CourseApplicationForm $form,
        array $snapshot,
    ): CourseApplication {
        $latestVersion = CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $form->course_id)
            ->max('version');

        $application = CourseApplication::create([
            'user_id' => $user->user_id,
            'course_id' => $form->course_id,
            'form_id' => $form->id,
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
            'snapshot' => $snapshot,
            'version' => ($latestVersion ?? 0) + 1,
            'submitted_at' => now(),
        ]);

        $this->syncCourseApplicationStatus($user, $form->course_id, CourseApplication::STATUS_PENDING_REVIEW);
        $this->notifyStaffOfSubmission($application);
        $application->load(['course']);
        $this->mail->send($user, \App\Models\CourseApplicationReviewTemplate::KEY_RECEIVED, $application);

        return $application;
    }

    public function syncCourseApplicationStatus(User $user, int $courseId, string $status): void
    {
        if (! Schema::hasTable('course_user_application_status')) {
            return;
        }

        CourseUserApplicationStatus::query()->updateOrCreate(
            [
                'user_id' => $user->user_id,
                'course_id' => $courseId,
            ],
            ['application_status' => $status]
        );
    }

    public function courseApplicationStatus(User $user, int $courseId): ?string
    {
        if (! Schema::hasTable('course_user_application_status')) {
            return null;
        }

        return CourseUserApplicationStatus::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $courseId)
            ->value('application_status');
    }

    public function isApprovedForCourse(User $user, int $courseId): bool
    {
        if ($this->roster->studentEnrolledCourses($user)->contains('course_id', $courseId)) {
            return true;
        }

        return $this->courseApplicationStatus($user, $courseId) === CourseApplication::STATUS_APPROVED;
    }

    public function formEnabledForCourse(int $courseId): bool
    {
        if (! Schema::hasTable('course_application_forms')) {
            return false;
        }

        return CourseApplicationForm::query()
            ->where('course_id', $courseId)
            ->where('is_enabled', true)
            ->exists();
    }

    public function redirectRouteFor(User $user, int $courseId): string
    {
        $status = $this->courseApplicationStatus($user, $courseId);

        if ($status === null) {
            return 'courses.apply';
        }

        return match ($status) {
            CourseApplication::STATUS_NEEDS_CORRECTION => 'courses.application.edit',
            default => 'courses.application.status',
        };
    }

    public function redirectParamsFor(int $courseId): array
    {
        return ['course' => $courseId];
    }

    private function notifyStaffOfSubmission(CourseApplication $application): void
    {
        $application->loadMissing(['user', 'course', 'form']);
        $staff = $this->roster->courseStaff((string) $application->course_id);

        foreach ($staff as $member) {
            if (! $member->canAccessAdminCourseApplications()) {
                continue;
            }

            $this->notifications->createOrUpdate(
                $member,
                'course_application_submitted',
                __('course_applications.notification_submitted_title', [
                    'course' => $application->course?->title ?? '',
                ]),
                __('course_applications.notification_submitted_body', [
                    'name' => $application->user?->displayName() ?? '',
                    'course' => $application->course?->title ?? '',
                ]),
                route('admin.course-applications.show', $application),
                CourseApplication::class,
                $application->id,
                metadata: [
                    'course_id' => $application->course_id,
                    'applicant_user_id' => $application->user_id,
                ],
                dedupeKey: "course_application_submitted:{$application->id}",
            );
        }
    }
}
