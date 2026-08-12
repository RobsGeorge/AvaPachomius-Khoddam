<?php

namespace Tests\Feature\Rbac;

use App\Models\Church;
use App\Models\Permission;
use App\Services\RoleTemplateService;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class RoleTemplateSyncTest extends EventModuleTestCase
{
    public function test_roles_sync_templates_merges_missing_keys_onto_church_clones(): void
    {
        Artisan::call('permissions:sync');

        $templates = app(RoleTemplateService::class);
        $templates->ensureChurchTemplates();

        $church = Church::create([
            'slug' => 'role-sync-'.uniqid(),
            'name' => 'Role Sync Church',
            'status' => 'active',
        ]);
        \App\Models\ChurchCapability::create([
            'church_id' => $church->church_id,
            'capability_key' => 'church_management',
            'enabled' => true,
            'config' => [],
        ]);
        $church->unsetRelation('capabilities');

        $cloned = $templates->cloneTemplatesIntoChurch($church);
        $admin = $cloned['church-admin'] ?? null;
        $this->assertNotNull($admin);

        $cycleIds = Permission::whereIn('key', ['church.cycle.view', 'church.cycle.manage'])->pluck('permission_id');
        $this->assertNotEmpty($cycleIds);
        $admin->permissions()->detach($cycleIds);
        $this->assertFalse(
            $admin->fresh()->permissions()->where('permissions.key', 'church.cycle.view')->exists()
        );

        $merged = $templates->mergeTemplatePermissionsIntoChurchClones($church);
        $this->assertGreaterThan(0, $merged);

        $admin->refresh();
        $this->assertTrue($admin->permissions()->where('permissions.key', 'church.cycle.view')->exists());
        $this->assertTrue($admin->permissions()->where('permissions.key', 'church.cycle.manage')->exists());
    }
}
