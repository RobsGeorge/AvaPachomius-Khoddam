<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Collection;

class StudentRosterService
{
    public function accessibleCourses(User $user): Collection
    {
        if ($user->isAdmin() || ($user->is_superadmin ?? false)) {
            return Course::query()->orderBy('title')->get();
        }

        return $user->courses()->orderBy('title')->get();
    }

    public function authorizeCourse(User $user, string $courseId): void
    {
        if ($user->isAdmin() || ($user->is_superadmin ?? false)) {
            return;
        }

        abort_unless(
            $user->courses()->where('course.course_id', $courseId)->exists(),
            403
        );
    }

    public function enrolledStudents(Course|string $course): Collection
    {
        $courseId = $course instanceof Course ? $course->course_id : $course;
        $studentRoleId = $this->studentRoleId();

        $studentIds = UserCourseRole::query()
            ->where('course_id', $courseId)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }

    public function courseStaff(string $courseId): Collection
    {
        $roleIds = Role::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(role_name) = ?', ['admin'])
                    ->orWhereRaw('LOWER(role_name) = ?', ['instructor']);
            })
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        $userIds = UserCourseRole::query()
            ->where('course_id', $courseId)
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('first_name')
            ->get()
            ->unique('email')
            ->values();
    }

    public function studentsWithBirthdayInMonth(Collection $students, int $month): Collection
    {
        return $students
            ->filter(fn (User $student) => $student->date_of_birth
                && (int) $student->date_of_birth->month === $month)
            ->sortBy(fn (User $student) => $student->date_of_birth->day)
            ->values();
    }

    private function studentRoleId(): ?int
    {
        return Role::query()
            ->whereRaw('LOWER(role_name) = ?', ['student'])
            ->value('role_id');
    }
}
