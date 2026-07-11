<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Collection;

class CourseEnrollmentService
{
    /** @return Collection<int, User> */
    public function enrolledStudents(int|string $courseId): Collection
    {
        $studentRoleId = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->value('role_id');

        $studentIds = UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->pluck('user_id')
            ->unique();

        return User::whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->get();
    }

    /** @return Collection<int, User> */
    public function courseStaff(int|string $courseId, bool $includeArchived = false): Collection
    {
        $staffRoleIds = Role::query()
            ->whereRaw('LOWER(role_name) IN (?, ?)', ['admin', 'instructor'])
            ->pluck('role_id');

        $query = UserCourseRole::where('course_id', $courseId)
            ->whereIn('role_id', $staffRoleIds);

        if (! $includeArchived) {
            $query->whereNull('staff_archived_at');
        }

        $userIds = $query->pluck('user_id')->unique();

        return User::whereIn('user_id', $userIds)->get();
    }

    public function studentEnrollment(int $userId, int|string $courseId): ?UserCourseRole
    {
        $studentRoleId = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->value('role_id');

        return UserCourseRole::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->first();
    }
}
