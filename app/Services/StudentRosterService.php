<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentRosterService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function accessibleCourses(User $user): Collection
    {
        if ($user->is_superadmin ?? false) {
            return Course::query()->orderBy('title')->get();
        }

        $courseIds = $user->userCourseRoles()
            ->activeStaff()
            ->pluck('course_id')
            ->merge(
                $user->userCourseRoles()
                    ->staffArchivedOnly()
                    ->whereHas('role', fn ($q) => $q->whereRaw('LOWER(role_name) = ?', ['student'])
                        ->orWhere('slug', 'student'))
                    ->pluck('course_id')
            )
            ->unique();

        return Course::query()
            ->whereIn('course_id', $courseIds)
            ->orderBy('title')
            ->get();
    }

    public function studentEnrolledCourses(User $user): Collection
    {
        $studentRoleIds = Role::studentRoleIds();

        if ($studentRoleIds->isEmpty()) {
            return collect();
        }

        $courseIds = UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->whereIn('role_id', $studentRoleIds)
            ->pluck('course_id')
            ->unique();

        return Course::query()
            ->whereIn('course_id', $courseIds)
            ->orderBy('title')
            ->get();
    }

    public function authorizeCourse(User $user, string $courseId): void
    {
        if ($user->is_superadmin ?? false) {
            return;
        }

        $course = Course::find($courseId);
        abort_unless($course && $this->resolver->hasCourseAccess($user, $course), 403);
    }

    public function enrolledStudents(Course|string $course): Collection
    {
        $courseId = $course instanceof Course ? $course->course_id : $course;
        $studentRoleIds = Role::studentRoleIds();

        $studentIds = UserCourseRole::query()
            ->where('course_id', $courseId)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }

    public function courseStaff(string $courseId, bool $includeArchived = false): Collection
    {
        $staffRoleIds = Role::staffRoleIds();

        if ($staffRoleIds->isEmpty()) {
            return collect();
        }

        $query = UserCourseRole::query()
            ->where('course_id', $courseId)
            ->whereIn('role_id', $staffRoleIds);

        if (! $includeArchived) {
            $query->activeStaff();
        }

        $userIds = $query->pluck('user_id')->unique();

        return User::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('first_name')
            ->get()
            ->unique('email')
            ->values();
    }

    public function studentsWithBirthdayInMonth(Collection $students, int $month, ?Carbon $on = null): Collection
    {
        $on ??= now(config('attendance.timezone', config('app.timezone')));

        return $students
            ->filter(fn (User $student) => $student->date_of_birth
                && (int) $student->date_of_birth->month === $month)
            ->sortBy(fn (User $student) => [
                $student->isBirthdayToday($on) ? 0 : 1,
                $student->date_of_birth->day,
            ])
            ->values();
    }

    public function studentsWithBirthdayToday(Collection $students, ?Carbon $on = null): Collection
    {
        $on ??= now(config('attendance.timezone', config('app.timezone')));

        return $students
            ->filter(fn (User $student) => $student->isBirthdayToday($on))
            ->sortBy(fn (User $student) => $student->displayName())
            ->values();
    }
}
