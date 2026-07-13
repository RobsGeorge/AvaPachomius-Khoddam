<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Models\UserSystemRole;

class RoleAssignmentNotificationService
{
    public function __construct(
        private NotificationGeneratorService $generator,
    ) {}

    public function notifyCourseRole(User $assignee, Role $role, Course $course, UserCourseRole $assignment): void
    {
        $title = __('notifications.generated.role_assigned_course_title', [
            'role' => $role->role_name,
            'course' => $course->title,
        ]);
        $body = __('notifications.generated.role_assigned_course_body', [
            'role' => $role->role_name,
            'course' => $course->title,
        ]);

        $this->generator->createOrUpdate(
            $assignee,
            'role_assigned',
            $title,
            $body,
            route('dashboard'),
            UserCourseRole::class,
            $assignment->user_course_role_id,
            UserNotification::PRIORITY_NORMAL,
            [
                'scope' => 'course',
                'course_id' => $course->course_id,
                'role_id' => $role->role_id,
                'role_name' => $role->role_name,
                'course_title' => $course->title,
            ],
            "role_assigned:course:{$course->course_id}:user:{$assignee->user_id}",
        );
    }

    public function notifySystemRole(User $assignee, Role $role, UserSystemRole $assignment): void
    {
        $title = __('notifications.generated.role_assigned_system_title', [
            'role' => $role->role_name,
        ]);
        $body = __('notifications.generated.role_assigned_system_body', [
            'role' => $role->role_name,
        ]);

        $this->generator->createOrUpdate(
            $assignee,
            'role_assigned',
            $title,
            $body,
            route('dashboard'),
            UserSystemRole::class,
            $assignment->user_system_role_id,
            UserNotification::PRIORITY_NORMAL,
            [
                'scope' => 'system',
                'role_id' => $role->role_id,
                'role_name' => $role->role_name,
                'course_title' => '',
            ],
            "role_assigned:system:{$role->role_id}:user:{$assignee->user_id}",
        );
    }
}
