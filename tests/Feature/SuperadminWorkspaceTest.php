<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use App\Services\RolePreviewService;
use App\Support\ChurchHost;
use App\Support\SuperadminWorkspace;
use App\Tenancy\TenantContext;
use Tests\Support\EventModuleTestCase;

class SuperadminWorkspaceTest extends EventModuleTestCase
{
    public function test_superadmin_on_console_hides_member_nav(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test']);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-console@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->get(route('superadmin.index'))
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
        $this->get(route('admin.translations.index'))->assertForbidden();
    }

    public function test_platform_enter_shows_member_chrome(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test']);

        $church = Church::main();
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-platform@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.churches.platform-enter', $church))
            ->assertRedirect(ChurchHost::url($church, '/dashboard'));

        $this->assertTrue(\App\Services\PlatformAccessService::isActive());
        $this->assertTrue(RolePreviewService::superadminBypassesPermissions($super));
        $this->assertTrue(SuperadminWorkspace::showsMemberChrome($super));
    }

    public function test_church_registry_view_as_starts_preview(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test']);

        $church = Church::main();
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'workspace-registry@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('superadmin.churches.view-as', $church))
            ->assertRedirect(ChurchHost::url($church, '/dashboard'));

        $this->assertTrue(RolePreviewService::isChurchAdminMode());
    }
}
