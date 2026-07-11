<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Validation\ValidationException;

class CourseRoleAssignmentService
{
    public function assign(User $user, int $courseId, int $roleId): UserCourseRole
    {
        $exists = UserCourseRole::where('user_id', $user->user_id)
            ->where('course_id', $courseId)
            ->where('role_id', $roleId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'duplicate' => __('registration_review.role_already_assigned'),
            ]);
        }

        return UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $courseId,
            'role_id' => $roleId,
        ]);
    }

    /** @param list<array{course_id: int, role_id: int}> $assignments */
    public function assignMany(User $user, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $this->assign($user, (int) $assignment['course_id'], (int) $assignment['role_id']);
        }
    }

    public function coursesForPicker()
    {
        return Course::orderBy('title')->get();
    }

    public function rolesForPicker()
    {
        return Role::orderBy('role_name')->get();
    }
}
