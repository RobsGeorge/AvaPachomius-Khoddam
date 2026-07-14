<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session as SessionStore;
use Illuminate\Validation\ValidationException;

class CourseContextService
{
    public const SESSION_KEY = 'current_course_id';

    public function requiresCourseContext(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return ! ($user->is_superadmin ?? false);
    }

    public function supportsOptionalCourseContext(?User $user): bool
    {
        return $user instanceof User && ($user->is_superadmin ?? false);
    }

    public function isSystemWideMode(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $this->supportsOptionalCourseContext($user) && ! $this->currentCourse($user);
    }

    /**
     * Courses the user may activate. When the service layer is ready, results are
     * scoped to the given service (current service if omitted). Members with no
     * service context get an empty list so service must be chosen first.
     *
     * @return Collection<int, array{course: Course, role: Role|null, enrollment: UserCourseRole|null}>
     */
    public function selectableCourses(User $user, ?ChurchService $withinService = null): Collection
    {
        $serviceReady = ChurchService::tableReady();
        $withinService ??= $serviceReady
            ? app(ServiceContextService::class)->currentService($user)
            : null;

        if ($serviceReady && ! $withinService && ! ($user->is_superadmin ?? false)) {
            return collect();
        }

        if ($this->supportsOptionalCourseContext($user)) {
            $query = Course::query()->orderByDesc('year')->orderBy('title');
            if ($withinService) {
                $query->where('service_id', $withinService->service_id);
            }

            return $query->get()
                ->filter(fn (Course $course) => $course->isSelectableForContext())
                ->map(fn (Course $course) => [
                    'course' => $course,
                    'role' => null,
                    'enrollment' => null,
                ])
                ->values();
        }

        $enrollments = UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->activeStaff()
            ->with(['course', 'role'])
            ->get();

        return $enrollments
            ->filter(function (UserCourseRole $enrollment) use ($withinService) {
                $course = $enrollment->course;
                if (! $course instanceof Course || ! $course->isSelectableForContext()) {
                    return false;
                }

                if ($withinService) {
                    return (int) ($course->service_id ?? 0) === (int) $withinService->service_id;
                }

                return true;
            })
            ->map(fn (UserCourseRole $enrollment) => [
                'course' => $enrollment->course,
                'role' => $enrollment->role,
                'enrollment' => $enrollment,
            ])
            ->unique(fn (array $row) => $row['course']->course_id)
            ->sortBy(fn (array $row) => $row['course']->localizedTitle())
            ->values();
    }

    public function currentCourse(?User $user = null): ?Course
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if (! $this->requiresCourseContext($user) && ! $this->supportsOptionalCourseContext($user)) {
            return null;
        }

        $courseId = SessionStore::get(self::SESSION_KEY);
        if (! $courseId) {
            return null;
        }

        $course = Course::query()->find($courseId);
        if (! $course || ! $this->userCanSelectCourse($user, $course)) {
            return null;
        }

        return $course;
    }

    public function setCurrentCourse(User $user, int $courseId): Course
    {
        $course = Course::query()->findOrFail($courseId);

        if (! $this->userCanSelectCourse($user, $course)) {
            throw ValidationException::withMessages([
                'course_id' => [__('course_context.invalid_course')],
            ]);
        }

        // Service owns courses — selecting a course activates its parent service first.
        if (ChurchService::tableReady() && $course->service_id) {
            $service = ChurchService::find($course->service_id);
            $serviceContext = app(ServiceContextService::class);
            if ($service && $serviceContext->userCanSelectService($user, $service)) {
                SessionStore::put(ServiceContextService::SESSION_KEY, $service->service_id);
            }
        }

        SessionStore::put(self::SESSION_KEY, $course->course_id);

        return $course;
    }

    public function clearCurrentCourse(): void
    {
        SessionStore::forget(self::SESSION_KEY);
    }

    public function syncFromRoute(User $user, mixed $courseParam): void
    {
        if (! $this->requiresCourseContext($user) && ! $this->supportsOptionalCourseContext($user)) {
            return;
        }

        $course = $this->resolveCourseParam($courseParam);
        if (! $course || ! $this->userCanSelectCourse($user, $course)) {
            return;
        }

        SessionStore::put(self::SESSION_KEY, $course->course_id);

        // Keep service aligned when course is activated from a deep link.
        if (ChurchService::tableReady() && $course->service_id) {
            $service = ChurchService::find($course->service_id);
            $serviceContext = app(ServiceContextService::class);
            if ($service && $serviceContext->userCanSelectService($user, $service)) {
                SessionStore::put(ServiceContextService::SESSION_KEY, $service->service_id);
            }
        }
    }

    /**
     * Pick the active course from a query override, session context, or first accessible option.
     *
     * @param  \Illuminate\Support\Collection<int, Course>  $accessibleCourses
     */
    public function resolveAccessibleCourse(User $user, Collection $accessibleCourses, ?string $requestedCourseId = null): ?Course
    {
        if ($accessibleCourses->isEmpty()) {
            return null;
        }

        if ($requestedCourseId !== null && $requestedCourseId !== '') {
            $match = $accessibleCourses->firstWhere('course_id', $requestedCourseId);
            if ($match) {
                return $match;
            }
        }

        $current = $this->currentCourse($user);
        if ($current) {
            $match = $accessibleCourses->firstWhere('course_id', $current->course_id);
            if ($match) {
                return $match;
            }
        }

        return $accessibleCourses->first();
    }

    private function resolveCourseParam(mixed $courseParam): ?Course
    {
        if ($courseParam instanceof Course) {
            return $courseParam;
        }

        if (is_numeric($courseParam)) {
            return Course::query()->find((int) $courseParam);
        }

        if (is_string($courseParam) && $courseParam !== '' && ctype_digit($courseParam)) {
            return Course::query()->find((int) $courseParam);
        }

        return null;
    }

    public function userCanSelectCourse(User $user, Course $course): bool
    {
        if (! $course->isSelectableForContext()) {
            return false;
        }

        if ($this->supportsOptionalCourseContext($user)) {
            return true;
        }

        return UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->activeStaff()
            ->exists();
    }

    public function resolvePostLoginRoute(User $user): string
    {
        if (! $this->requiresCourseContext($user)) {
            return 'dashboard';
        }

        // Service → course. Resolve department context before academic year.
        if (ChurchService::tableReady()) {
            $serviceContext = app(ServiceContextService::class);
            if ($serviceContext->requiresServiceContext($user)) {
                $serviceContext->autoSelectSingleService($user);

                if (! $serviceContext->currentService($user)
                    && $serviceContext->selectableServices($user)->count() > 1) {
                    return 'services.select';
                }

                $serviceContext->clearIncompatibleCourse($user);
            }
        }

        $selectable = $this->selectableCourses($user);

        if ($selectable->isEmpty()) {
            $this->clearCurrentCourse();

            return 'dashboard';
        }

        $current = $this->currentCourse($user);
        if ($current) {
            return 'dashboard';
        }

        if ($selectable->count() === 1) {
            $this->setCurrentCourse($user, (int) $selectable->first()['course']->course_id);

            return 'dashboard';
        }

        return 'courses.select';
    }

    public function autoSelectSingleCourse(User $user): void
    {
        if (! $this->requiresCourseContext($user) || $this->currentCourse($user)) {
            return;
        }

        $selectable = $this->selectableCourses($user);
        if ($selectable->count() === 1) {
            $this->setCurrentCourse($user, (int) $selectable->first()['course']->course_id);
        }
    }

    public function brandingCss(?Course $course): ?string
    {
        if (! $course) {
            return null;
        }

        $colors = $course->brandingColors();
        $rules = [];

        if (! empty($colors['primary'])) {
            $primary = $colors['primary'];
            $rules[] = "--color-primary: {$primary};";
            $rules[] = "--color-primary-hover: {$primary};";
        }

        if (! empty($colors['accent'])) {
            $rules[] = "--color-accent: {$colors['accent']};";
        }

        if ($rules === []) {
            return null;
        }

        return ':root { '.implode(' ', $rules).' }';
    }
}
