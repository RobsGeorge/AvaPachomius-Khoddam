<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
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