<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\RegistrationApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistrationEnrollmentService
{
    public function __construct(
        private CourseApplicationService $courseApplications,
        private CourseApplicationFormService $formService,
        private CourseApplicationSnapshotPrefillService $snapshotPrefill,
    ) {}

    /** @return Collection<int, ChurchService> */
    public function eligibleServices(): Collection
    {
        if (! ChurchService::tableReady()) {
            return collect();
        }

        return ChurchService::query()
            ->where('status', ChurchService::STATUS_ACTIVE)
            ->orderBy('title')
            ->get()
            ->filter(fn (ChurchService $service) => $this->eligibleCoursesForService($service->service_id)->isNotEmpty())
            ->values();
    }

    /** @return Collection<int, Course> */
    public function eligibleCoursesForService(int $serviceId): Collection
    {
        return Course::query()
            ->where('service_id', $serviceId)
            ->orderBy('title')
            ->get()
            ->filter(fn (Course $course) => $this->courseApplications->formEnabledForCourse((int) $course->course_id))
            ->values();
    }

    public function completeWithEnrollment(User $user, int $serviceId, int $courseId): void
    {
        $course = Course::query()->where('course_id', $courseId)->first();

        if (! $course || (int) $course->service_id !== $serviceId) {
            throw ValidationException::withMessages([
                'course_id' => __('register.enrollment_course_invalid'),
            ]);
        }

        if (! $this->courseApplications->formEnabledForCourse($courseId)) {
            throw ValidationException::withMessages([
                'course_id' => __('register.enrollment_course_not_accepting'),
            ]);
        }

        $form = $this->formService->getOrCreateForCourse($course);

        if (! $form->is_enabled) {
            throw ValidationException::withMessages([
                'course_id' => __('register.enrollment_course_not_accepting'),
            ]);
        }

        if (! $form->default_role_id) {
            throw ValidationException::withMessages([
                'course_id' => __('register.enrollment_course_not_configured'),
            ]);
        }

        DB::transaction(function () use ($user, $course, $form) {
            $user->is_verified = false;

            if (Schema::hasColumn('user', 'registration_completed')) {
                $user->registration_completed = true;
            }

            if (Schema::hasColumn('user', 'application_status')) {
                $user->application_status = RegistrationApplication::STATUS_PENDING_REVIEW;
            } else {
                $user->is_verified = true;
            }

            if (Schema::hasColumn('user', 'registration_intent_course_id')) {
                $user->registration_intent_course_id = $course->course_id;
            }

            if (Schema::hasColumn('user', 'created_at') && $user->created_at === null) {
                $user->created_at = now();
            }

            if (Schema::hasColumn('user', 'updated_at')) {
                $user->updated_at = now();
            }

            $user->save();

            if (! Schema::hasColumn('user', 'application_status')) {
                PendingRegistrationService::assignDefaultStudentRole($user);

                return;
            }

            $snapshot = $this->snapshotPrefill->buildFromUser($user, $form);
            $this->courseApplications->createFromSubmission($user, $form, $snapshot);
        });
    }
}
