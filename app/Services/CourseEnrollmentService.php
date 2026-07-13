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
        $studentRoleIds = $this->studentRoleIds();

        $studentIds = UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->pluck('user_id')
            ->unique();

        return User::whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->get();
    }

    /** @return Collection<int, User> */
    public function courseStaff(int|string $courseId, bool $includeArchived = false): Collection
    {
        $staffRoleIds = $this->staffRoleIds();

        $query = UserCourseRole::where('course_id', $courseId)
            ->whereIn('role_id', $staffRoleIds);

        if (! $includeArchived) {
            $query->activeStaff();
        }

        $userIds = $query->pluck('user_id')->unique();

        return User::whereIn('user_id', $userIds)->get();
    }

    public function studentEnrollment(int $userId, int|string $courseId): ?UserCourseRole
    {
        $studentRoleIds = $this->studentRoleIds();

        return UserCourseRole::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->first();
    }

    private function studentRoleIds(): Collection
    {
        return Role::studentRoleIds();
    }

    private function staffRoleIds(): Collection
    {
        return Role::staffRoleIds();
    }
}
