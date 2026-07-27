<?php

namespace Tests\Feature\Observability;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Models\ObservabilityEvent;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class ChurchObservabilityIsolationTest extends EventModuleTestCase
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

    public function test_church_admin_sees_only_own_church_incidents(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $other = Church::create(['slug' => 'obs-other-'.uniqid(), 'name' => 'Other Obs', 'status' => 'active']);

        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'church-a-marker'),
            'message' => 'CHURCH_A_SECRET_MARKER',
            'church_id' => $church->church_id,
        ]);
        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'church-b-marker'),
            'message' => 'CHURCH_B_SECRET_MARKER',
            'church_id' => $other->church_id,
        ]);

        TenantContext::set($church);

        $this->assertTrue($admin->canInChurch('church.observability.view', $church));

        $this->actingAs($admin)
            ->get(route('admin.observability.index'))
            ->assertOk()
            ->assertSee('CHURCH_A_SECRET_MARKER', false)
            ->assertDontSee('CHURCH_B_SECRET_MARKER', false);
    }

    public function test_church_scoped_query_hides_other_tenant_when_context_bound(): void
    {
        config(['tenancy.enabled' => true]);

        $churchA = Church::query()->where('slug', config('tenancy.main_slug'))->firstOrFail();
        $churchB = Church::create([
            'name' => 'Other Obs Church',
            'slug' => 'other-obs-'.uniqid(),
            'status' => 'active',
        ]);

        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'iso-a'),
            'message' => 'ISO_A',
            'church_id' => $churchA->church_id,
        ]);
        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'iso-b'),
            'message' => 'ISO_B',
            'church_id' => $churchB->church_id,
        ]);

        TenantContext::set($churchA);

        $messages = ObservabilityEvent::query()->pluck('message');
        $this->assertTrue($messages->contains('ISO_A'));
        $this->assertFalse($messages->contains('ISO_B'));
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        foreach (['church_management', 'announcements', 'reporting'] as $key) {
            ChurchCapability::query()->firstOrCreate(
                ['church_id' => $church->church_id, 'capability_key' => $key],
                ['enabled' => true]
            );
        }

        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => 'obs-church-admin@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = $roles[$templateSlug];
        $permIds = \App\Models\Permission::whereIn('key', [
            'church.observability.view',
            'church.observability.export',
        ])->pluck('permission_id');
        $role->permissions()->syncWithoutDetaching($permIds);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }
}
