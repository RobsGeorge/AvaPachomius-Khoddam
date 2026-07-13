<?php

namespace Tests\Feature;

use App\Mail\RoleAssignmentMail;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Services\CourseRoleAssignmentService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class RoleAssignmentNotificationTest extends EventModuleTestCase
{
    public function test_course_role_assignment_creates_portal_notification_and_sends_email(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'role-admin@example.com']);
        $assignee = $this->createUser(['email' => 'role-assignee@example.com']);
        $course = $this->createCourse(['title' => 'Notify Course']);
        $managerRole = $this->courseRoleWithPermissions($course, 'manager', ['role.manage']);

        $this->actingAs($admin)
            ->post(route('courses.roles.assignments.store', $course), [
                'user_id' => $assignee->user_id,
                'role_id' => $managerRole->role_id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $assignee->user_id)
                ->where('type', 'role_assigned')
                ->exists()
        );

        Mail::assertSent(RoleAssignmentMail::class, function (RoleAssignmentMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });
    }

    public function test_assign_or_update_skips_notification_when_role_unchanged(): void
    {
        Mail::fake();

        $assignee = $this->createUser(['email' => 'unchanged@example.com']);
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'helper', ['role.manage']);
        $this->assignCourseRole($assignee, $course, $role);

        app(CourseRoleAssignmentService::class)->assignOrUpdate($assignee, $course->course_id, $role->role_id);

        $this->assertSame(
            0,
            UserNotification::query()
                ->where('user_id', $assignee->user_id)
                ->where('type', 'role_assigned')
                ->count()
        );

        Mail::assertNothingSent();
    }

    public function test_role_change_triggers_new_notification(): void
    {
        Mail::fake();

        $assignee = $this->createUser(['email' => 'changed@example.com']);
        $course = $this->createCourse();
        $roleA = $this->courseRoleWithPermissions($course, 'role-a', ['exam.view']);
        $roleB = $this->courseRoleWithPermissions($course, 'role-b', ['role.manage']);

        UserCourseRole::create([
            'user_id' => $assignee->user_id,
            'course_id' => $course->course_id,
            'role_id' => $roleA->role_id,
        ]);

        app(CourseRoleAssignmentService::class)->assignOrUpdate(
            $assignee,
            $course->course_id,
            $roleB->role_id
        );

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $assignee->user_id)
                ->where('type', 'role_assigned')
                ->exists()
        );
    }

    public function test_superadmin_can_update_role_assignment_email_templates(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);

        $this->actingAs($super)
            ->get(route('roles.hub', ['section' => 'email-templates']))
            ->assertOk()
            ->assertSee(__('rbac.section_email_templates'));
    }

    public function test_duplicate_course_role_assignment_shows_error_flash(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $assignee = $this->createUser(['email' => 'dup@example.com']);
        $course = $this->createCourse();
        $managerRole = $this->courseRoleWithPermissions($course, 'manager', ['role.manage']);
        $this->assignCourseRole($assignee, $course, $managerRole);

        $this->actingAs($admin)
            ->post(route('courses.roles.assignments.store', $course), [
                'user_id' => $assignee->user_id,
                'role_id' => $managerRole->role_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('pages.duplicate_role_assignment'));
    }
}
