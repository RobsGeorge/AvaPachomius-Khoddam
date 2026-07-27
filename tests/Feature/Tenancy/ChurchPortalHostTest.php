<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Models\Priest;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\ChurchHost;
use App\Support\NavigationHub;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * Console host (admin.*) is platform-superadmin only. Church users must use
 * {slug}.{base}; confession/appointment routes require a bound tenant.
 */
class ChurchPortalHostTest extends EventModuleTestCase
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
        app(RoleTemplateService::class)->ensureChurchTemplates();
    }

    public function test_priest_on_console_host_is_redirected_to_church_portal(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
            'app.url' => 'https://admin.test',
        ]);

        [$church, $priestUser] = $this->seedDemoChurchWithPriest();

        $this->actingAs($priestUser)
            ->get('https://admin.test/church/confession')
            ->assertRedirect(ChurchHost::url($church, '/church/confession'));
    }

    public function test_priest_login_on_console_redirects_to_church_host(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
            'app.url' => 'https://admin.test',
        ]);

        [$church, $priestUser] = $this->seedDemoChurchWithPriest();

        $this->from('https://admin.test/login')
            ->post('https://admin.test/login', [
                'email' => $priestUser->email,
                'password' => 'password',
            ])
            ->assertRedirect(ChurchHost::url($church, '/dashboard'));
    }

    public function test_superadmin_stays_on_console_host(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
            'app.url' => 'https://admin.test',
        ]);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'console-super@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->get('https://admin.test/superadmin')
            ->assertOk();
    }

    public function test_priest_and_member_reach_calendars_on_church_host(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
            'app.url' => 'https://test',
        ]);

        [$church, $priestUser, $roles] = $this->seedDemoChurchWithPriest();
        $member = $this->createUser([
            'email' => 'portal-member@example.com',
            'registration_completed' => true,
        ]);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $member->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $member->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        $base = 'https://'.ChurchHost::hostFor($church);

        $this->actingAs($priestUser)
            ->get($base.'/church/confession')
            ->assertOk();

        $this->actingAs($priestUser)
            ->get($base.'/church/appointments')
            ->assertOk();

        $this->actingAs($member)
            ->get($base.'/church/confession')
            ->assertOk();

        $this->actingAs($member)
            ->get($base.'/church/appointments')
            ->assertOk();
    }

    public function test_service_nav_shows_confession_on_church_host_not_console(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
            'app.url' => 'https://test',
        ]);

        [$church, $priestUser] = $this->seedDemoChurchWithPriest();

        TenantContext::clear();
        $consoleLinks = collect(NavigationHub::serviceLinks($priestUser));
        $this->assertFalse(
            $consoleLinks->contains(fn ($link) => str_contains((string) ($link['url'] ?? ''), '/church/confession')),
            'Console host must not show church confession nav (no bound tenant).'
        );

        TenantContext::set($church);
        $churchLinks = collect(NavigationHub::serviceLinks($priestUser));
        $this->assertTrue(
            $churchLinks->contains(fn ($link) => str_contains((string) ($link['url'] ?? ''), '/church/confession')),
            'Church host must show confession nav for priest.'
        );
    }

    /** @return array{0: Church, 1: User, 2: array<string, \App\Models\Role>} */
    private function seedDemoChurchWithPriest(): array
    {
        $church = Church::create([
            'slug' => 'demo-stmark-portal',
            'name' => 'St Mark Portal Test',
            'status' => 'active',
            'settings' => ['demo' => true],
        ]);

        foreach (array_keys((array) config('capabilities')) as $key) {
            ChurchCapability::create([
                'church_id' => $church->church_id,
                'capability_key' => $key,
                'enabled' => true,
                'config' => null,
            ]);
        }

        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        app(RoleTemplateService::class)->mergeTemplatePermissionsIntoChurchClones($church);

        $priestUser = $this->createUser([
            'email' => 'priest.portal-'.uniqid('', true).'@example.com',
            'registration_completed' => true,
        ]);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'role_id' => $roles['priest']->role_id,
            'assigned_at' => now(),
        ]);

        TenantContext::set($church);
        Priest::create([
            'user_id' => $priestUser->user_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);
        TenantContext::clear();

        return [$church, $priestUser, $roles];
    }
}
