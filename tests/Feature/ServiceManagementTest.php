<?php

namespace Tests\Feature;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserSystemRole;
use App\Support\NavigationHub;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ServiceManagementTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('service') || ! Schema::hasColumn('course', 'service_id')) {
            $this->markTestSkipped('Service schema not ready.');
        }
    }

    public function test_guest_cannot_access_service_management(): void
    {
        $this->get(route('admin.services.index'))->assertRedirect();
    }

    public function test_regular_user_forbidden_from_service_management(): void
    {
        $user = $this->createUser(['email' => 'svc-mgmt-denied@example.com']);

        $this->actingAs($user)
            ->get(route('admin.services.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_create_and_link_service(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-super@example.com']);
        $orphanCourse = $this->createCourse(['title' => 'Orphan Year', 'status' => Course::STATUS_ACTIVE]);

        $this->actingAs($super)
            ->post(route('admin.services.store'), [
                'title' => 'Liturgy Prep',
                'title_ar' => 'إعداد الليتورجيا',
                'title_en' => 'Liturgy Prep',
                'description' => 'Service panel test',
                'clone_templates' => '1',
            ])
            ->assertRedirect(route('admin.services.index'));

        $service = ChurchService::query()->where('title', 'Liturgy Prep')->first();
        $this->assertNotNull($service);

        $this->actingAs($super)
            ->post(route('admin.services.link-course', $service), [
                'course_id' => $orphanCourse->course_id,
            ])
            ->assertRedirect(route('admin.services.edit', $service));

        $this->assertSame($service->service_id, $orphanCourse->fresh()->service_id);
    }

    public function test_system_role_with_platform_service_crud_can_manage(): void
    {
        $user = $this->createUser(['email' => 'svc-mgmt-system@example.com']);
        $this->grantSystemPermission($user, 'platform.service_crud');

        $this->assertTrue(NavigationHub::hasService($user));
        $urls = collect(NavigationHub::serviceLinks($user))->pluck('url');
        $this->assertTrue($urls->contains(route('admin.services.index')));

        $this->actingAs($user)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee(__('service.manage_title'), false);

        $this->actingAs($user)
            ->get(route('hubs.service'))
            ->assertOk();
    }

    public function test_cannot_archive_service_with_active_courses(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-arch@example.com']);
        $service = $this->createService(['title' => 'Keep Open']);
        $this->createCourse([
            'title' => 'Active Year',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->actingAs($super)
            ->from(route('admin.services.index'))
            ->post(route('admin.services.archive', $service))
            ->assertRedirect(route('admin.services.index'))
            ->assertSessionHasErrors('service');

        $this->assertSame(ChurchService::STATUS_ACTIVE, $service->fresh()->status);
    }

    public function test_superadmin_course_create_requires_service_id(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-course@example.com']);
        $service = $this->createService(['title' => 'Parent Service']);

        $this->actingAs($super)
            ->post(route('superadmin.courses.store'), [
                'title' => 'Year One',
                'description' => 'Linked course',
                'year' => 2026,
                'default_session_start_time' => '09:00',
                'service_id' => $service->service_id,
            ])
            ->assertRedirect(route('superadmin.courses'));

        $course = Course::query()->where('title', 'Year One')->first();
        $this->assertNotNull($course);
        $this->assertSame($service->service_id, (int) $course->service_id);
    }

    protected function grantSystemPermission(\App\Models\User $user, string $permissionKey): void
    {
        $perm = Permission::query()->where('key', $permissionKey)->first();
        $this->assertNotNull($perm, "Permission {$permissionKey} must exist after sync.");

        $role = Role::create([
            'role_name' => 'Svc Crud '.$user->user_id,
            'role_decription' => 'svc-crud',
            'slug' => 'svc-crud-'.$user->user_id,
            'course_id' => null,
            'is_system' => true,
            'is_template' => false,
        ]);
        $role->permissions()->sync([$perm->permission_id]);

        UserSystemRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);

        \Illuminate\Support\Facades\Cache::flush();
    }
}
