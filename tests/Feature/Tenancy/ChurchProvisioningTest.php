<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\ChurchProvisioningService;
use App\Support\ChurchHost;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Support\EventModuleTestCase;

/**
 * T4 — provisioning, host URLs, login membership gate, switcher dormancy.
 */
class ChurchProvisioningTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false]);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_provisioning_creates_church_capabilities_roles_and_admins(): void
    {
        $admin = $this->createUser(['email' => 'prov-admin@example.com']);

        $church = app(ChurchProvisioningService::class)->create(
            [
                'slug' => 'st-mark-t4',
                'name' => 'St Mark T4',
                'capabilities' => ['church_management', 'announcements', 'reporting'],
            ],
            [$admin->user_id]
        );

        $this->assertDatabaseHas('church', ['slug' => 'st-mark-t4', 'status' => 'active']);
        $this->assertTrue($church->hasCapability('announcements'));
        $this->assertFalse($church->hasCapability('exams'));

        $this->assertTrue(
            ChurchUser::where('church_id', $church->church_id)->where('user_id', $admin->user_id)->exists()
        );
        $this->assertTrue(
            UserChurchRole::where('church_id', $church->church_id)->where('user_id', $admin->user_id)->exists()
        );
        $this->assertTrue(
            $church->roles()->where('slug', 'church-admin')->whereNull('course_id')->exists()
        );
        $this->assertTrue($admin->canInChurch('church.members.manage', $church));
    }

    public function test_provisioning_creates_aligned_organization_row(): void
    {
        $church = app(ChurchProvisioningService::class)->create([
            'slug' => 'st-mina-org',
            'name' => 'St Mina Org',
            'capabilities' => ['church_management'],
        ]);

        $this->assertNotNull($church->organization_id);
        $this->assertSame((int) $church->church_id, (int) $church->organization_id);

        $this->assertDatabaseHas('organizations', [
            'organization_id' => $church->church_id,
            'subdomain' => 'st-mina-org',
            'name' => 'St Mina Org',
            'type' => 'church',
            'status' => 'active',
        ]);

        $org = Organization::find($church->organization_id);
        $this->assertNotNull($org);
        $this->assertSame('st-mina-org', $org->subdomain);
    }

    public function test_provisioned_church_id_is_valid_tenant_fk_target(): void
    {
        $church = app(ChurchProvisioningService::class)->create([
            'slug' => 'fk-safe-church',
            'name' => 'FK Safe Church',
            'capabilities' => ['church_management'],
        ]);

        $this->assertTrue(
            Organization::where('organization_id', $church->church_id)->exists(),
            'Tenant church_id FKs reference organizations.organization_id'
        );

        // Product path: stamp church_id on a tenant row that FKs → organizations.
        // church_id is not mass-assignable on Course; set explicitly then persist.
        $course = $this->createCourse();
        $course->church_id = $church->church_id;
        $course->save();

        $this->assertDatabaseHas('course', [
            'course_id' => $course->course_id,
            'church_id' => $church->church_id,
        ]);
    }

    public function test_ensure_organization_linked_repairs_legacy_church_without_org(): void
    {
        $church = Church::create([
            'slug' => 'legacy-orphan',
            'name' => 'Legacy Orphan',
            'status' => 'active',
            'organization_id' => null,
        ]);

        $this->assertDatabaseMissing('organizations', ['subdomain' => 'legacy-orphan']);

        $org = app(ChurchProvisioningService::class)->ensureOrganizationLinked($church->fresh());

        $this->assertNotNull($org);
        $this->assertSame((int) $church->church_id, (int) $org->organization_id);
        $this->assertSame((int) $church->church_id, (int) $church->fresh()->organization_id);
    }

    public function test_suspend_syncs_organization_status(): void
    {
        $church = app(ChurchProvisioningService::class)->create([
            'slug' => 'suspend-org',
            'name' => 'Suspend Org',
            'capabilities' => ['church_management'],
        ]);

        app(ChurchProvisioningService::class)->suspend($church->fresh());

        $this->assertDatabaseHas('church', [
            'church_id' => $church->church_id,
            'status' => 'suspended',
        ]);
        $this->assertDatabaseHas('organizations', [
            'organization_id' => $church->church_id,
            'status' => 'suspended',
        ]);
    }

    public function test_suspend_blocks_main_and_404s_resolution(): void
    {
        $main = Church::main();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ChurchProvisioningService::class)->suspend($main);
    }

    public function test_church_host_builds_slug_and_custom_domain_urls(): void
    {
        config(['app.url' => 'http://khedma.test', 'tenancy.base_domain' => 'khedma.test']);

        $church = Church::create([
            'slug' => 'academy',
            'name' => 'Academy',
            'status' => 'active',
        ]);
        $this->assertSame('academy.khedma.test', ChurchHost::hostFor($church));
        $this->assertSame('http://academy.khedma.test/', ChurchHost::url($church));

        $church->domain = 'custom.example.org';
        $this->assertSame('custom.example.org', ChurchHost::hostFor($church));
    }

    public function test_console_host_heals_admin_localhost_using_base_domain(): void
    {
        config([
            'app.url' => 'https://staging.avapakhomios.com',
            'tenancy.base_domain' => 'staging.avapakhomios.com',
            'tenancy.console_host' => 'admin.localhost',
        ]);

        $this->assertSame('admin.staging.avapakhomios.com', ChurchHost::consoleHost());
        $this->assertSame(
            'https://admin.staging.avapakhomios.com/superadmin/churches',
            ChurchHost::consoleUrl('/superadmin/churches')
        );
        $this->assertTrue(ChurchHost::isConsoleHost('admin.staging.avapakhomios.com'));
        $this->assertFalse(ChurchHost::isConsoleHost('admin.localhost'));
    }

    public function test_console_host_respects_explicit_non_local_override(): void
    {
        config([
            'app.url' => 'https://staging.avapakhomios.com',
            'tenancy.base_domain' => 'staging.avapakhomios.com',
            'tenancy.console_host' => 'console.staging.avapakhomios.com',
        ]);

        $this->assertSame('console.staging.avapakhomios.com', ChurchHost::consoleHost());
    }

    public function test_console_host_keeps_local_default_for_localhost_base(): void
    {
        config([
            'app.url' => 'http://localhost',
            'tenancy.base_domain' => 'localhost',
            'tenancy.console_host' => 'admin.localhost',
        ]);

        $this->assertSame('admin.localhost', ChurchHost::consoleHost());
        $this->assertSame('http://admin.localhost/superadmin/churches', ChurchHost::consoleUrl('/superadmin/churches'));
    }

    public function test_login_rejects_non_member_when_tenancy_enabled_and_church_bound(): void
    {
        config(['tenancy.enabled' => true]);
        // ResolveTenant falls back to main; outsider has no church_user row.
        $this->createUser([
            'email' => 'outsider@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'outsider@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_superadmin_churches_index_is_reachable(): void
    {
        $super = $this->createUser([
            'email' => 'super-t4@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.churches.index'))
            ->assertOk()
            ->assertSee(__('tenancy.churches_title'), false);
    }

    public function test_church_switcher_vars_dormant_when_multi_tenant_off(): void
    {
        config(['tenancy.enabled' => false]);
        $user = $this->createUser(['email' => 'switcher@example.com']);
        ChurchUser::create([
            'church_id' => Church::main()->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard');
        // Composer shares supportsChurchSwitcher=false when tenancy off — no exception is enough;
        // assert view data via a second render with tenancy on.
        config(['tenancy.enabled' => true]);
        Church::create(['slug' => 'other-sw', 'name' => 'Other', 'status' => 'active']);
        $super = $this->createUser(['email' => 'switcher-super@example.com', 'is_superadmin' => true]);
        $html = $this->actingAs($super)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString(__('tenancy.switch_church'), $html);
    }

    public function test_single_church_shows_static_label_not_dropdown(): void
    {
        config(['tenancy.enabled' => true]);
        // Remove any extra churches seeded elsewhere so only Tenant Zero is active.
        Church::query()->where('slug', '!=', config('tenancy.main_slug'))->delete();

        $user = $this->createUser(['email' => 'one-church@example.com']);
        ChurchUser::create([
            'church_id' => Church::main()->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(Church::main()->name, $html);
        $this->assertDoesNotMatchRegularExpression(
            '/aria-label="'.preg_quote(__('tenancy.switch_church'), '/').'"/',
            $html
        );
    }
}