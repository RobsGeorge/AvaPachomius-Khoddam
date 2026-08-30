<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use App\Services\BreakGlass\BreakGlassService;
use App\Services\RolePreviewService;
use App\Services\RoleTemplateService;
use App\Support\ChurchHost;
use App\Support\SuperadminWorkspace;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Support\Facades\URL;
use Tests\Support\EventModuleTestCase;

class SuperadminWorkspaceTest extends EventModuleTestCase
{
    public function test_superadmin_on_console_hides_member_nav(): void
    {
        // Laravel's test client always rebuilds the request URI via url(), which
        // reads Laravel's UrlGenerator singleton — already booted (and its root
        // cached) from phpunit.xml's forced APP_URL before this test's config()
        // call runs. Symfony's Request::create() then overwrites whatever
        // HTTP_HOST withServerVariables() set with that URI's host. config()
        // alone doesn't reach the cached generator — URL::forceRootUrl() does
        // (the same mechanism ChurchHost::temporarySignedRoute() already uses).
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test']);
        URL::forceRootUrl('http://admin.test');

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-console@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->get('/superadmin')
            ->assertOk()
            ->assertDontSee(__('nav.academic'), false)
            ->assertSee(__('tenancy.console'), false);
    }

    public function test_superadmin_on_church_host_without_workflow_redirects_to_console(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test', 'tenancy.base_domain' => 'test']);

        $church = Church::query()->where('slug', config('tenancy.main_slug'))->first();
        $this->assertNotNull($church);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-redirect@example.com',
            'registration_completed' => true,
        ]);

        TenantContext::set($church);

        $host = ChurchHost::hostFor($church);

        $this->withServerVariables(['HTTP_HOST' => $host])
            ->actingAs($super)
            ->get(route('dashboard'))
            ->assertRedirect(ChurchHost::consoleUrl('/superadmin/churches'));
    }

    public function test_view_as_church_masks_superadmin_bypass(): void
    {
        config(['tenancy.enabled' => true]);

        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-view-church@example.com',
            'registration_completed' => true,
        ]);

        $adminRole = $church->roles()->where('slug', 'church-admin')->whereNull('course_id')->first();
        $this->assertNotNull($adminRole);

        RolePreviewService::startChurchAdminRole($super, $church, request());

        $this->actingAs($super);
        $this->assertFalse(RolePreviewService::superadminBypassesPermissions($super));
        $this->assertTrue(RolePreviewService::isChurchAdminMode());
        $this->assertFalse(\App\Support\NavigationHub::hasSuperadmin($super));
        $this->get(route('admin.translations.index'))->assertForbidden();
    }

    public function test_view_as_church_hides_superadmin_nav_on_dashboard(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test', 'tenancy.base_domain' => 'test']);

        $church = Church::main();
        TenantContext::set($church);
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-view-nav@example.com',
            'registration_completed' => true,
        ]);

        RolePreviewService::startChurchAdminRole($super, $church, request());

        $host = ChurchHost::hostFor($church);
        // route('dashboard') reads Laravel's cached UrlGenerator (rooted at
        // phpunit.xml's forced APP_URL) — without forcing it to $host first, the
        // dispatched request's real Host ends up 'localhost' regardless of
        // withServerVariables(), and ResolveTenant re-binds TenantContext off that
        // instead of the intended church (see test_superadmin_on_console_hides_member_nav).
        URL::forceRootUrl('http://'.$host);

        $this->withServerVariables(['HTTP_HOST' => $host])
            ->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('workspace.view_as_banner_title'), false)
            ->assertDontSee(__('nav.superadmin'), false);
    }

    public function test_platform_enter_shows_member_chrome(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test', 'tenancy.base_domain' => 'test']);

        $church = Church::main();
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-platform@example.com',
            'registration_completed' => true,
        ]);

        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $this->assertNotNull($org);
        app(BreakGlassService::class)->grant($super, $super, $org, 'Workspace test platform access', 60);

        $url = ChurchHost::temporarySignedRoute($church, 'superadmin.churches.platform-enter.start', $church);
        $host = ChurchHost::hostFor($church);

        $this->withServerVariables(['HTTP_HOST' => $host])
            ->actingAs($super)
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(\App\Services\PlatformAccessService::isActive());
        $this->assertTrue(RolePreviewService::superadminBypassesPermissions($super));
        $this->assertTrue(SuperadminWorkspace::showsMemberChrome($super));
        $this->assertTrue(\App\Support\NavigationHub::hasSuperadmin($super));
    }

    public function test_church_registry_view_as_starts_preview(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test', 'tenancy.base_domain' => 'test']);

        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-registry@example.com',
            'registration_completed' => true,
        ]);

        $url = ChurchHost::temporarySignedRoute($church, 'superadmin.churches.view-as.start', $church);
        $host = ChurchHost::hostFor($church);

        $this->withServerVariables(['HTTP_HOST' => $host])
            ->actingAs($super)
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(RolePreviewService::isChurchAdminMode());
    }

    public function test_signed_church_workflow_links_validate_when_generated_on_console_host(): void
    {
        config([
            'app.url' => 'https://admin.staging.example',
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.staging.example',
            'tenancy.base_domain' => 'staging.example',
        ]);

        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-signed-console@example.com',
            'registration_completed' => true,
        ]);

        $churchHost = ChurchHost::hostFor($church);

        $this->withServerVariables(['HTTP_HOST' => 'admin.staging.example']);

        $viewAsUrl = ChurchHost::temporarySignedRoute($church, 'superadmin.churches.view-as.start', $church);
        $platformUrl = ChurchHost::temporarySignedRoute($church, 'superadmin.churches.platform-enter.start', $church);

        $this->assertStringContainsString($churchHost.'/superadmin/churches/', $viewAsUrl);
        $this->assertStringContainsString($churchHost.'/superadmin/churches/', $platformUrl);
        $this->assertStringContainsString('signature=', $viewAsUrl);
        $this->assertStringContainsString('signature=', $platformUrl);

        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $this->assertNotNull($org);
        app(BreakGlassService::class)->grant($super, $super, $org, 'Signed console platform access', 60);

        $this->withServerVariables(['HTTP_HOST' => $churchHost])
            ->actingAs($super)
            ->get($viewAsUrl)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->withServerVariables(['HTTP_HOST' => $churchHost])
            ->actingAs($super)
            ->get($platformUrl)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');
    }
}
