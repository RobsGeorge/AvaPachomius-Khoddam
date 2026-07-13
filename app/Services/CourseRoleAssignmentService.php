<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Validation\ValidationException;

class CourseRoleAssignmentService
{
    public function __construct(
        private RoleAssignmentNotificationService $notifications,
    ) {}

    public function assign(User $user, int $courseId, int $roleId, bool $notify = true): UserCourseRole
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

        $assignment = UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $courseId,
            'role_id' => $roleId,
        ]);

        if ($notify) {
            $this->notifyAssignment($user, $courseId, $roleId, $assignment);
        }

        return $assignment;
    }

    public function assignOrUpdate(User $user, int $courseId, int $roleId, bool $notify = true): UserCourseRole
    {
        $existing = UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $courseId)
            ->first();

        if ($existing && (int) $existing->role_id === $roleId) {
            return $existing;
        }

        $assignment = UserCourseRole::updateOrCreate(
            ['user_id' => $user->user_id, 'course_id' => $courseId],
            ['role_id' => $roleId]
        );

        if ($notify) {
            $this->notifyAssignment($user, $courseId, $roleId, $assignment);
        }

        return $assignment;
    }

    /** @param list<array{course_id: int, role_id: int}> $assignments */
    public function assignMany(User $user, array $assignments, bool $notify = true): void
    {
        foreach ($assignments as $assignment) {
            $this->assign($user, (int) $assignment['course_id'], (int) $assignment['role_id'], $notify);
        }
    }

    public function coursesForPicker()
    {
        return Course::orderBy('title')->get();
    }

    public function rolesForPicker(?int $courseId = null)
    {
        $picker = app(RolePickerService::class);

        return $courseId !== null
            ? $picker->forCourse($courseId)
            : $picker->groupedByCourse()->flatten(1);
    }

    public function rolesGroupedByCourse()
    {
        return app(RolePickerService::class)->groupedByCourse();
    }

    private function notifyAssignment(User $user, int $courseId, int $roleId, UserCourseRole $assignment): void
    {
        $role = Role::find($roleId);
        $course = Course::find($courseId);

        if (! $role || ! $course) {
            return;
        }

        $this->notifications->notifyCourseRole($user, $role, $course, $assignment);
    }
}
