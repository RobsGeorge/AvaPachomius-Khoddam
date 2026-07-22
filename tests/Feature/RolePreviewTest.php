<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Services\CourseContextService;
use App\Services\RolePreviewService;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class RolePreviewTest extends EventModuleTestCase
{
    public function test_superadmin_can_start_course_role_preview(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'role-preview-super@example.com']);
        $course = $this->createCourse(['title' => 'Preview Course', 'status' => Course::STATUS_ACTIVE]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        $this->actingAs($super)
            ->post(route('superadmin.role-preview'), [
                'course_id' => $course->course_id,
                'role_id' => $studentRole->role_id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(RolePreviewService::isActive());
        $this->assertSame($course->course_id, session(CourseContextService::SESSION_KEY));
        $this->assertSame($studentRole->role_id, session(RolePreviewService::SESSION_ROLE_ID));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('pages.role_preview_banner_title'), false);
    }

    public function test_course_role_preview_restricts_permissions_and_admin_routes(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'role-preview-restrict@example.com']);
        $course = $this->createCourse(['title' => 'Restricted Course', 'status' => Course::STATUS_ACTIVE]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        RolePreviewService::startCourseRole($super, $course, $studentRole, request());

        $this->actingAs($super);

        $this->assertFalse($super->canInCourse('role.manage', $course));
        $this->assertTrue($super->canInCourse('exam.view', $course));
        $this->assertTrue($super->isStudent());

        $this->get(route('admin.translations.index'))->assertForbidden();

        $this->post(route('superadmin.role-preview.stop'))
            ->assertRedirect(route('superadmin.security'));

        $this->assertTrue(RolePreviewService::superadminBypassesPermissions($super));
        $this->assertTrue($super->canInCourse('role.manage', $course));
    }

    public function test_superadmin_can_start_general_system_role_preview(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'role-preview-general@example.com']);

        $systemRole = Role::create([
            'role_name' => 'Preview System Helper',
            'role_decription' => 'Helper',
            'slug' => 'preview-helper',
            'course_id' => null,
            'is_system' => true,
            'is_template' => false,
        ]);

        $perm = \App\Models\Permission::query()->where('key', 'translation.manage')->first();
        if ($perm) {
            $systemRole->permissions()->sync([$perm->permission_id]);
        }

        $this->actingAs($super)
            ->post(route('superadmin.role-preview'), [
                'general_role' => '1',
                'role_id' => $systemRole->role_id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(RolePreviewService::isActive());
        $this->assertTrue(RolePreviewService::isGeneral());
        $this->assertNull(session(CourseContextService::SESSION_KEY));

        $this->get(route('dashboard'))->assertOk();

        $this->actingAs($super);

        if ($perm) {
            $this->assertTrue($super->canInSystem('translation.manage'));
        }
    }

    public function test_cannot_start_role_preview_while_impersonating(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'role-preview-imp@example.com']);
        $target = $this->createUser(['email' => 'role-preview-target@example.com', 'registration_completed' => true]);
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        $this->actingAs($super)
            ->post(route('superadmin.impersonate'), ['user_id' => $target->user_id])
            ->assertRedirect(route('dashboard'));

        $this->expectException(ValidationException::class);
        RolePreviewService::startCourseRole($super, $course, $role, request());
    }
}
