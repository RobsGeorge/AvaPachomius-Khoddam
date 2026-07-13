<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\RolesHubService;
use App\Support\NavigationHub;
use Tests\Support\EventModuleTestCase;

class RolesHubTest extends EventModuleTestCase
{
    public function test_superadmin_can_access_roles_hub_with_all_sections(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);

        $this->actingAs($super)
            ->get(route('roles.hub'))
            ->assertOk()
            ->assertSee(__('rbac.hub_title'))
            ->assertSee(__('rbac.section_course'))
            ->assertSee(__('rbac.section_assignments'))
            ->assertSee(__('rbac.manage_templates'))
            ->assertSee(__('rbac.system_roles'))
            ->assertSee(__('rbac.group_visibility'));
    }

    public function test_course_admin_sees_only_course_section(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'manager', ['role.manage']);
        $this->assignCourseRole($user, $course, $role);

        $this->actingAs($user)
            ->get(route('roles.hub', ['course' => $course->course_id, 'section' => 'course']))
            ->assertOk()
            ->assertSee(__('rbac.section_course'))
            ->assertDontSee('id="section-templates"', false)
            ->assertDontSee('id="section-system"', false);
    }

    public function test_unauthorized_user_cannot_access_roles_hub(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);
        $this->assignCourseRole($user, $course, $role);

        $this->actingAs($user)
            ->get(route('roles.hub'))
            ->assertForbidden();
    }

    public function test_legacy_routes_redirect_to_hub(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);

        $this->actingAs($super)
            ->get(route('superadmin.templates.index'))
            ->assertRedirect(route('roles.hub', ['section' => 'templates']));

        $this->actingAs($super)
            ->get(route('superadmin.system-roles.index'))
            ->assertRedirect(route('roles.hub', ['section' => 'system']));

        $this->actingAs($super)
            ->get(route('superadmin.group-visibility.index'))
            ->assertRedirect(route('roles.hub', ['section' => 'visibility']));
    }

    public function test_course_roles_index_redirects_to_hub(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);
        $course = $this->createCourse();

        $this->actingAs($super)
            ->get(route('courses.roles.index', $course))
            ->assertRedirect(route('roles.hub', ['course' => $course->course_id, 'section' => 'course']));
    }

    public function test_navigation_exposes_single_roles_hub_link(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);
        $hub = app(RolesHubService::class);

        $urls = collect(NavigationHub::systemLinks($super))->pluck('url');

        $this->assertTrue($urls->contains($hub->hubUrl()));
        $this->assertFalse($urls->contains(route('user-course-roles.index')));
    }

    public function test_superadmin_navigation_replaces_scattered_role_links(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);
        $sections = NavigationHub::superadminSections($super);
        $labels = collect($sections)->flatMap(fn ($section) => collect($section['links'])->pluck('label'));

        $this->assertTrue($labels->contains(__('rbac.hub_title')));
        $this->assertFalse($labels->contains(__('pages.all_role_assignments')));
        $this->assertFalse($labels->contains(__('rbac.manage_templates')));
    }
}
