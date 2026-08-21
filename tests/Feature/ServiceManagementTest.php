<?php

namespace Tests\Feature;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StructureTemplate;
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

        $payload = [
            'title' => 'Liturgy Prep',
            'title_ar' => 'إعداد الليتورجيا',
            'title_en' => 'Liturgy Prep',
            'description' => 'Service panel test',
            'clone_templates' => '1',
        ];

        // Services bind to a structure-template anchor (master-plan §15); store()
        // requires structure_template_id once the column exists.
        if (Schema::hasColumn('service', 'structure_template_id')) {
            $payload['structure_template_id'] = StructureTemplate::create([
                'key' => 'svc_mgmt_test_'.uniqid(),
                'name_ar' => 'قالب الخدمة',
                'name_en' => 'Service template',
                'levels' => [
                    ['key' => 'cohort', 'label_ar' => 'فوج', 'label_en' => 'Cohort'],
                ],
                'anchors' => [
                    'enrollment_level' => 'cohort',
                ],
            ])->structure_template_id;
        }

        $this->actingAs($super)
            ->post(route('admin.services.store'), $payload)
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

    public function test_superadmin_courses_loads_when_a_legacy_service_has_no_slug(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-null-slug@example.com']);
        $service = $this->createService(['title' => 'Legacy Unslugged Service']);
        $service->forceFill(['slug' => null])->saveQuietly();
        $this->assertNull($service->fresh()->slug);

        $this->actingAs($super)
            ->get(route('superadmin.courses'))
            ->assertOk()
            ->assertSee(__('pages.manage_services_and_courses'), false)
            ->assertSee('Legacy Unslugged Service', false);
    }

    public function test_superadmin_courses_loads_without_structure_templates_table(): void
    {
        if (! Schema::hasTable('structure_templates')) {
            $this->markTestSkipped('structure_templates not present to drop.');
        }

        Schema::drop('structure_templates');

        try {
            $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-no-tpl@example.com']);

            $this->actingAs($super)
                ->get(route('superadmin.courses'))
                ->assertOk()
                ->assertSee(__('pages.manage_services_and_courses'), false);
        } finally {
            // DROP TABLE is DDL and cannot roll back on SQLite; restore so later
            // tests in this process still see the T8a registry.
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_08_10_000001_create_structure_templates_table.php',
                '--force' => true,
            ]);
        }
    }

    public function test_superadmin_course_create_requires_service_id(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-course@example.com']);
        $service = $this->createService(['title' => 'Parent Service']);

        $this->actingAs($super)
            ->get(route('superadmin.courses'))
            ->assertOk()
            ->assertSee(__('pages.manage_courses'), false);

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

        $form = \App\Models\CourseApplicationForm::query()
            ->where('course_id', $course->course_id)
            ->first();
        $this->assertNotNull($form);
        $this->assertTrue($form->is_enabled);
        $this->assertNotNull($form->default_role_id);
    }

    public function test_superadmin_can_update_course_from_manage_page(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-mgmt-course-edit@example.com']);
        $service = $this->createService(['title' => 'Edit Parent Service']);
        $course = $this->createCourse([
            'title' => 'Original Title',
            'description' => 'Original description',
            'year' => 2025,
            'service_id' => $service->service_id,
            'default_session_start_time' => '09:00:00',
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.courses'))
            ->assertOk()
            ->assertSee(__('pages.edit_course'), false)
            ->assertSee('Original Title', false);

        $this->actingAs($super)
            ->put(route('superadmin.courses.update', $course->course_id), [
                'title' => 'Updated Title',
                'title_ar' => 'عنوان محدث',
                'title_en' => 'Updated EN',
                'description' => 'Updated description',
                'description_ar' => 'وصف محدث',
                'description_en' => 'Updated EN description',
                'year' => 2026,
                'default_session_start_time' => '10:30',
                'service_id' => $service->service_id,
            ])
            ->assertRedirect(route('superadmin.courses'))
            ->assertSessionHas('success');

        $course->refresh();
        $this->assertSame('Updated Title', $course->title);
        $this->assertSame('عنوان محدث', $course->title_ar);
        $this->assertSame('Updated EN', $course->title_en);
        $this->assertSame('Updated description', $course->description);
        $this->assertSame(2026, (int) $course->year);
        $this->assertSame('10:30:00', $course->default_session_start_time);
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
