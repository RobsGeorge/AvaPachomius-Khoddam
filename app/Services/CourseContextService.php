<?php

namespace App\Services;

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

    /** @return Collection<int, array{course: Course, role: Role, enrollment: UserCourseRole}> */
    public function selectableCourses(User $user): Collection
    {
        $enrollments = UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->activeStaff()
            ->with(['course', 'role'])
            ->get();

        return $enrollments
            ->filter(function (UserCourseRole $enrollment) {
                $course = $enrollment->course;

                return $course instanceof Course && $course->isSelectableForContext();
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

        if (! $this->requiresCourseContext($user)) {
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

        SessionStore::put(self::SESSION_KEY, $course->course_id);

        return $course;
    }

    public function clearCurrentCourse(): void
    {
        SessionStore::forget(self::SESSION_KEY);
    }

    public function syncFromRoute(User $user, mixed $courseParam): void
    {
        if (! $this->requiresCourseContext($user)) {
            return;
        }

        $course = $this->resolveCourseParam($courseParam);
        if (! $course || ! $this->userCanSelectCourse($user, $course)) {
            return;
        }

        SessionStore::put(self::SESSION_KEY, $course->course_id);
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
