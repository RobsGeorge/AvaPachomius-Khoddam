<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Services\ServiceContextService;
use App\Services\ServiceRoleAssignmentService;
use App\Support\NavigationHub;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class RolesHubContextTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('service') || ! Schema::hasTable('user_service_role')) {
            $this->markTestSkipped('Service schema not ready.');
        }
    }

    public function test_selected_service_hides_other_services_and_platform_tools(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'hub-scope-super@example.com']);
        $serviceA = $this->createService(['title' => 'Scoped Alpha Service']);
        $serviceB = $this->createService(['title' => 'Scoped Beta Service']);
        $courseA = $this->createCourse([
            'title' => 'Scoped Alpha Course',
            'service_id' => $serviceA->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $courseB = $this->createCourse([
            'title' => 'Scoped Beta Course',
            'service_id' => $serviceB->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $this->courseRoleWithPermissions($courseA, 'admin', ['role.manage']);
        $this->courseRoleWithPermissions($courseB, 'admin', ['role.manage']);

        $this->actingAs($super)
            ->withSession([ServiceContextService::SESSION_KEY => $serviceA->service_id])
            ->get(route('roles.hub', ['section' => 'service']))
            ->assertOk()
            ->assertSee(__('rbac.hub_intro_service', ['service' => $serviceA->localizedTitle()]), false)
            ->assertSee('Scoped Alpha Course', false)
            ->assertDontSee(__('rbac.hub_intro_service', ['service' => $serviceB->localizedTitle()]), false)
            ->assertDontSee('Scoped Beta Course', false)
            ->assertDontSee('id="section-templates"', false)
            ->assertDontSee('id="section-system"', false)
            ->assertDontSee('id="hub-service"', false)
            ->assertDontSee('id="hub-service-top"', false);
    }

    public function test_service_admin_does_not_see_another_service(): void
    {
        $serviceA = $this->createService(['title' => 'Admin Only Alpha']);
        $serviceB = $this->createService(['title' => 'Admin Only Beta']);
        $admin = $this->createUser(['email' => 'hub-scope-svc-admin@example.com']);
        $assigner = app(ServiceRoleAssignmentService::class);
        $assigner->assign($admin, $serviceA, $assigner->adminRoleFor($serviceA), asPrimary: true);

        $this->actingAs($admin)
            ->withSession([ServiceContextService::SESSION_KEY => $serviceA->service_id])
            ->get(route('roles.hub', ['section' => 'service']))
            ->assertOk()
            ->assertSee('Admin Only Alpha', false)
            ->assertDontSee('Admin Only Beta', false)
            ->assertDontSee('id="section-templates"', false)
            ->assertDontSee('id="hub-service"', false);
    }

    public function test_multi_service_admin_stays_on_nav_selected_service(): void
    {
        $serviceA = $this->createService(['title' => 'Dual Admin Alpha']);
        $serviceB = $this->createService(['title' => 'Dual Admin Beta']);
        $admin = $this->createUser(['email' => 'hub-scope-dual@example.com']);
        $assigner = app(ServiceRoleAssignmentService::class);
        $assigner->assign($admin, $serviceA, $assigner->adminRoleFor($serviceA), asPrimary: true);
        $assigner->assign($admin, $serviceB, $assigner->adminRoleFor($serviceB), allowCrossService: true);

        $this->actingAs($admin)
            ->withSession([ServiceContextService::SESSION_KEY => $serviceA->service_id])
            ->get(route('roles.hub', ['section' => 'service']))
            ->assertOk()
            ->assertSee('Dual Admin Alpha', false)
            ->assertSee(__('rbac.hub_intro_service', ['service' => $serviceA->localizedTitle()]), false)
            ->assertDontSee(__('rbac.hub_intro_service', ['service' => $serviceB->localizedTitle()]), false)
            ->assertDontSee('id="hub-service"', false)
            ->assertDontSee('id="hub-service-top"', false);
    }

    public function test_course_admin_does_not_see_other_service_assignments(): void
    {
        $serviceA = $this->createService(['title' => 'Course Scope Alpha']);
        $serviceB = $this->createService(['title' => 'Course Scope Beta']);
        $courseA = $this->createCourse([
            'title' => 'Course Scope Alpha Year',
            'service_id' => $serviceA->service_id,
        ]);
        $courseB = $this->createCourse([
            'title' => 'Course Scope Beta Year',
            'service_id' => $serviceB->service_id,
        ]);

        $admin = $this->createUser(['email' => 'hub-scope-course-admin@example.com']);
        $roleA = $this->courseRoleWithPermissions($courseA, 'manager', ['role.manage', 'user.assign_role']);
        $this->assignCourseRole($admin, $courseA, $roleA);

        $outsider = $this->createUser(['email' => 'hub-scope-outsider@example.com']);
        $roleB = $this->courseRoleWithPermissions($courseB, 'manager', ['role.manage']);
        $this->assignCourseRole($outsider, $courseB, $roleB);

        $this->actingAs($admin)
            ->withSession([ServiceContextService::SESSION_KEY => $serviceA->service_id])
            ->get(route('roles.hub', ['course' => $courseA->course_id, 'section' => 'course']))
            ->assertOk()
            ->assertSee('Course Scope Alpha Year', false)
            ->assertDontSee('Course Scope Beta Year', false)
            ->assertDontSee($outsider->email, false)
            ->assertDontSee('id="section-assignments"', false);
    }

    public function test_system_wide_superadmin_sees_platform_tools_only(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'hub-scope-system@example.com']);
        $this->createService(['title' => 'System Wide Hidden Service']);

        $this->actingAs($super)
            ->get(route('roles.hub'))
            ->assertOk()
            ->assertSee(__('rbac.hub_intro_system'))
            ->assertSee(__('rbac.manage_templates'))
            ->assertSee(__('rbac.system_roles'))
            ->assertDontSee('id="section-service"', false)
            ->assertDontSee('id="section-course"', false);
    }

    public function test_nav_roles_link_does_not_fall_back_to_another_service(): void
    {
        $serviceA = $this->createService(['title' => 'Nav Fallback Alpha']);
        $serviceB = $this->createService(['title' => 'Nav Fallback Beta']);
        $admin = $this->createUser(['email' => 'hub-scope-nav@example.com']);
        $assigner = app(ServiceRoleAssignmentService::class);
        $assigner->assign($admin, $serviceA, $assigner->adminRoleFor($serviceA), asPrimary: true);
        $assigner->assign($admin, $serviceB, $assigner->adminRoleFor($serviceB), allowCrossService: true);

        $url = collect(NavigationHub::serviceLinks($admin))
            ->pluck('url')
            ->first(fn ($candidate) => is_string($candidate) && str_contains($candidate, 'section=service'));

        $this->assertNotNull($url);
        $this->assertStringNotContainsString('service='.$serviceA->service_id, $url);
        $this->assertStringNotContainsString('service='.$serviceB->service_id, $url);

        app(ServiceContextService::class)->setCurrentService($admin, $serviceA);

        $scopedUrl = collect(NavigationHub::serviceLinks($admin))
            ->pluck('url')
            ->first(fn ($candidate) => is_string($candidate) && str_contains($candidate, 'section=service'));

        $this->assertNotNull($scopedUrl);
        $this->assertStringContainsString('service='.$serviceA->service_id, $scopedUrl);
        $this->assertStringNotContainsString('service='.$serviceB->service_id, $scopedUrl);
    }

    public function test_copy_roles_rejects_source_from_another_service(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'hub-scope-copy@example.com']);
        $serviceA = $this->createService(['title' => 'Copy From Alpha']);
        $serviceB = $this->createService(['title' => 'Copy From Beta']);
        $target = $this->createCourse(['title' => 'Copy Target', 'service_id' => $serviceA->service_id]);
        $source = $this->createCourse(['title' => 'Copy Source', 'service_id' => $serviceB->service_id]);
        $this->courseRoleWithPermissions($source, 'examiner', ['exam.grade', 'exam.view']);

        $this->actingAs($super)
            ->post(route('courses.roles.copy', $target), [
                'source_course_id' => $source->course_id,
            ])
            ->assertForbidden();
    }
}
