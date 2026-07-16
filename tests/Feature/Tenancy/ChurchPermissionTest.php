<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Models\Permission;
use App\Models\UserChurchRole;
use App\Services\CoursePermissionResolver;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T3-enforce — church-contextual permissions, capability ceiling, and church role templates.
 */
class ChurchPermissionTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_capability_catalog_lists_permission_keys(): void
    {
        $this->assertNotEmpty(config('capabilities.curriculum.permissions'));
        $this->assertContains('exam.grade', config('capabilities.exams.permissions'));
        $this->assertContains('church.members.manage', config('capabilities.church_management.permissions'));
    }

    public function test_can_in_church_aggregates_church_wide_grants(): void
    {
        $church = Church::main();
        $user = $this->createUser(['email' => 'church-admin@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $adminRole = $roles['church-admin'];

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $adminRole->role_id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($user->canInChurch('church.members.manage', $church));
        $this->assertTrue($user->canInChurch('announcement.view', $church));
    }

    public function test_different_roles_in_different_churches_resolve_independently(): void
    {
        $main = Church::main();
        $other = Church::create(['slug' => 'stmark-rbac', 'name' => 'St Mark', 'status' => 'active']);
        foreach (['announcements', 'reporting', 'church_management'] as $key) {
            ChurchCapability::create([
                'church_id' => $other->church_id,
                'capability_key' => $key,
                'enabled' => true,
            ]);
        }

        $user = $this->createUser(['email' => 'multi-church@example.com']);
        foreach ([$main, $other] as $church) {
            ChurchUser::create([
                'church_id' => $church->church_id,
                'user_id' => $user->user_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        $templates = app(RoleTemplateService::class);
        $mainRoles = $templates->cloneTemplatesIntoChurch($main);
        $otherRoles = $templates->cloneTemplatesIntoChurch($other);

        UserChurchRole::create([
            'church_id' => $main->church_id,
            'user_id' => $user->user_id,
            'role_id' => $mainRoles['church-admin']->role_id,
            'assigned_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $other->church_id,
            'user_id' => $user->user_id,
            'role_id' => $otherRoles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($user->canInChurch('church.members.manage', $main));
        $this->assertFalse($user->canInChurch('church.members.manage', $other));
        $this->assertTrue($user->canInChurch('announcement.view', $other));
    }

    public function test_capability_ceiling_blocks_permission_when_feature_disabled(): void
    {
        $church = Church::create(['slug' => 'no-exams-rbac', 'name' => 'No Exams', 'status' => 'active']);
        ChurchCapability::create([
            'church_id' => $church->church_id,
            'capability_key' => 'announcements',
            'enabled' => true,
        ]);

        $user = $this->createUser(['email' => 'ceiling@example.com']);
        $course = $this->createCourse(['church_id' => $church->church_id]);
        // createCourse may stamp via TenantContext; force the church id without mass-assign issues
        $course->forceFill(['church_id' => $church->church_id])->save();

        $role = $this->courseRoleWithPermissions($course, 'instructor', ['exam.grade', 'announcement.view']);
        $this->assignCourseRole($user, $course, $role);

        TenantContext::set($church);
        $this->assertFalse($user->canInCourse('exam.grade', $course->fresh()));
        $this->assertTrue($user->canInCourse('announcement.view', $course->fresh()));

        // Without a bound church the ceiling does not apply (production until cutover).
        TenantContext::clear();
        $this->assertTrue($user->canInCourse('exam.grade', $course->fresh()));
    }

    public function test_permission_version_bump_invalidates_church_cache(): void
    {
        $church = Church::main();
        $user = $this->createUser(['email' => 'cache-bust@example.com']);
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($user->canInChurch('announcement.view', $church));

        // Strip the grant's permissions and bump — cached "yes" must not stick.
        $roles['servant']->permissions()->sync([]);
        app(CoursePermissionResolver::class)->bumpChurchPermissionsVersion($church->fresh());

        $this->assertFalse($user->canInChurch('announcement.view', $church->fresh()));
    }

    public function test_church_templates_are_platform_null_and_clone_respects_capabilities(): void
    {
        $templates = app(RoleTemplateService::class)->ensureChurchTemplates();
        $this->assertCount(3, $templates);
        foreach ($templates as $role) {
            $this->assertTrue((bool) $role->is_template);
            $this->assertNull($role->church_id);
            $this->assertNull($role->course_id);
        }

        $sparse = Church::create(['slug' => 'sparse-rbac', 'name' => 'Sparse', 'status' => 'active']);
        ChurchCapability::create([
            'church_id' => $sparse->church_id,
            'capability_key' => 'church_management',
            'enabled' => true,
        ]);

        $cloned = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($sparse);
        $this->assertArrayHasKey('church-admin', $cloned);
        $this->assertEquals($sparse->church_id, $cloned['church-admin']->church_id);

        // church-admin still gets church.* keys; announcement.* only if capability enabled.
        $keys = $cloned['church-admin']->permissions()->pluck('key');
        $this->assertTrue($keys->contains('church.members.manage'));
        $this->assertFalse($keys->contains('announcement.manage'));
    }

    public function test_church_permission_keys_exist_after_sync(): void
    {
        foreach ([
            'church.configure', 'church.members.manage', 'church.role.manage',
            'finance.payroll.view', 'finance.payroll.manage',
            'finance.money_in.view', 'finance.money_in.manage',
        ] as $key) {
            $this->assertTrue(
                Permission::where('key', $key)->exists(),
                "Missing permission key: {$key}"
            );
        }
    }
}
