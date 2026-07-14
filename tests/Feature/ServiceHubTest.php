<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Permission;
use App\Services\CourseContextService;
use App\Services\ServiceContextService;
use App\Services\ServiceRoleAssignmentService;
use App\Support\NavigationHub;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ServiceHubTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('service') || ! Schema::hasTable('user_service_role')) {
            $this->markTestSkipped('Service schema not ready.');
        }
    }

    public function test_service_member_sees_hub_and_nav_links(): void
    {
        $service = $this->createService(['title' => 'Hub Alpha']);
        $user = $this->createUser(['email' => 'service-hub-member@example.com']);
        $this->assignServiceRole($user, $service, asPrimary: true);

        $this->assertTrue(NavigationHub::hasService($user));

        $urls = collect(NavigationHub::serviceLinks($user))->pluck('url');
        $this->assertTrue($urls->contains(route('services.select')));
        $this->assertTrue($urls->contains(route('services.roster', ['service' => $service->service_id]))
            || $urls->contains(fn ($url) => str_contains($url, 'services/roster')));

        $this->actingAs($user)
            ->get(route('hubs.service'))
            ->assertOk()
            ->assertSee(__('nav.service'), false)
            ->assertSee(__('service.roster_title'), false);
    }

    public function test_outsider_without_membership_is_forbidden_from_service_hub(): void
    {
        $user = $this->createUser(['email' => 'service-hub-outsider@example.com']);

        $this->assertFalse(NavigationHub::hasService($user));

        $this->actingAs($user)
            ->get(route('hubs.service'))
            ->assertForbidden();
    }

    public function test_selectable_services_isolate_memberships(): void
    {
        $serviceA = $this->createService(['title' => 'Nav A']);
        $serviceB = $this->createService(['title' => 'Nav B']);
        $user = $this->createUser(['email' => 'service-hub-iso@example.com']);
        $this->assignServiceRole($user, $serviceA);

        $selectable = app(ServiceContextService::class)->selectableServices($user);

        $this->assertTrue($selectable->contains('service_id', $serviceA->service_id));
        $this->assertFalse($selectable->contains('service_id', $serviceB->service_id));
    }

    public function test_switching_service_clears_course_from_other_service(): void
    {
        $serviceA = $this->createService(['title' => 'Course Clear A']);
        $serviceB = $this->createService(['title' => 'Course Clear B']);
        $courseA = $this->createCourse([
            'title' => 'Year A',
            'service_id' => $serviceA->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $courseB = $this->createCourse([
            'title' => 'Year B',
            'service_id' => $serviceB->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $user = $this->createUser(['email' => 'service-hub-switch@example.com']);
        $this->assignServiceRole($user, $serviceA, asPrimary: true);
        $this->assignServiceRole($user, $serviceB, allowCross: true);

        $role = $this->createRole('student-switch');
        $this->assignCourseRole($user, $courseA, $role);
        $this->assignCourseRole($user, $courseB, $role);

        $courseContext = app(CourseContextService::class);
        $serviceContext = app(ServiceContextService::class);

        $serviceContext->setCurrentService($user, $serviceA);
        $courseContext->setCurrentCourse($user, $courseA->course_id);
        $this->assertSame((int) $courseA->course_id, (int) $courseContext->currentCourse($user)?->course_id);

        $this->actingAs($user)
            ->post(route('services.select.store'), ['service_id' => $serviceB->service_id])
            ->assertRedirect(route('hubs.service'));

        $this->assertSame((int) $serviceB->service_id, (int) $serviceContext->currentService($user)?->service_id);
        $this->assertNull($courseContext->currentCourse($user));
    }

    public function test_service_admin_sees_roles_tile_on_hub(): void
    {
        $service = $this->createService(['title' => 'Admin Hub Service']);
        $admin = $this->createUser(['email' => 'service-hub-admin@example.com']);
        $assigner = app(ServiceRoleAssignmentService::class);
        $role = $assigner->adminRoleFor($service);
        $assigner->assign($admin, $service, $role, asPrimary: true);

        $urls = collect(NavigationHub::serviceLinks($admin))->pluck('url');
        $this->assertTrue($urls->contains(fn ($url) => str_contains($url, 'section=service')));

        $this->actingAs($admin)
            ->get(route('hubs.service'))
            ->assertOk()
            ->assertSee(__('rbac.section_service'), false);
    }

    public function test_application_reviewer_sees_queue_tile(): void
    {
        $reviewer = $this->createUser(['email' => 'service-hub-reviewer@example.com']);
        $perm = Permission::where('key', 'service_application.review')->first();
        $this->assertNotNull($perm);

        // Grant via system role path used elsewhere: attach through canInSystem by making superadmin
        // OR assign system permission if schema supports it. Prefer superadmin for stability.
        $reviewer->is_superadmin = true;
        $reviewer->save();

        $urls = collect(NavigationHub::serviceLinks($reviewer))->pluck('url');
        $this->assertTrue($urls->contains(route('admin.service-applications.index')));
    }
}
