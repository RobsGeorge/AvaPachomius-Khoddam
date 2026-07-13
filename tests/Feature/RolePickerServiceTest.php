<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\RolePickerService;
use Tests\Support\EventModuleTestCase;

class RolePickerServiceTest extends EventModuleTestCase
{
    public function test_for_course_returns_only_that_courses_assignable_roles(): void
    {
        $courseA = $this->createCourse(['title' => 'Alpha']);
        $courseB = $this->createCourse(['title' => 'Beta']);

        $roleA = $this->courseRoleWithPermissions($courseA, 'student', ['exam.view']);
        $roleB = $this->courseRoleWithPermissions($courseB, 'student', ['exam.view']);

        Role::create([
            'role_name' => 'Template Student',
            'role_decription' => 'Template',
            'slug' => 'template-student',
            'course_id' => null,
            'is_template' => true,
        ]);

        $picker = app(RolePickerService::class);
        $roles = $picker->forCourse($courseA->course_id);

        $this->assertCount(1, $roles);
        $this->assertSame($roleA->role_id, $roles->first()->role_id);
        $this->assertNotContains($roleB->role_id, $roles->pluck('role_id'));
    }

    public function test_grouped_by_course_separates_roles_per_course(): void
    {
        $courseA = $this->createCourse(['title' => 'Alpha']);
        $courseB = $this->createCourse(['title' => 'Beta']);

        $this->courseRoleWithPermissions($courseA, 'admin', ['role.manage']);
        $this->courseRoleWithPermissions($courseB, 'admin', ['role.manage']);

        $grouped = app(RolePickerService::class)->groupedByCourse();

        $this->assertCount(1, $grouped->get($courseA->course_id));
        $this->assertCount(1, $grouped->get($courseB->course_id));
    }

    public function test_distinct_eligible_role_names_deduplicates_across_courses(): void
    {
        $courseA = $this->createCourse(['title' => 'Alpha']);
        $courseB = $this->createCourse(['title' => 'Beta']);

        $this->courseRoleWithPermissions($courseA, 'student', ['exam.view']);
        $this->courseRoleWithPermissions($courseB, 'student', ['exam.view']);

        $names = app(RolePickerService::class)->distinctEligibleRoleNames();

        $this->assertCount(1, $names);
        $this->assertSame('student', strtolower($names->first()));
    }

    public function test_application_form_edit_shows_each_role_once_for_course(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $courseA = $this->createCourse(['title' => 'Alpha']);
        $courseB = $this->createCourse(['title' => 'Beta']);

        $studentA = $this->courseRoleWithPermissions($courseA, 'student', ['exam.view']);
        $this->courseRoleWithPermissions($courseB, 'student', ['exam.view']);
        $this->courseRoleWithPermissions($courseA, 'admin', ['role.manage']);

        $response = $this->actingAs($admin)
            ->get(route('admin.courses.application-form.edit', $courseA->course_id));

        $response->assertOk();
        $response->assertSee('value="'.$studentA->role_id.'"', false);
        $content = $response->getContent();
        $this->assertSame(
            1,
            substr_count($content, 'value="'.$studentA->role_id.'"'),
            'Each role option should appear once in the picker markup.'
        );
    }
}
