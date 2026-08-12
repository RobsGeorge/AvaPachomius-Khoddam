<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Billing\EntitlementResolver;
use App\Billing\QuotaGuard;
use App\Billing\ServiceSubscriptionService;
use App\Models\BillingAccount;
use App\Models\ChurchCapability;
use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class ServiceSubscriptionTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_service_addon_merges_on_top_of_church_floor(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $service = $this->createServiceForChurch($church);
        $addon = SubscriptionPlan::where('slug', 'service-addon')->first()
            ?? $this->createServiceOnlyPlan();

        app(ServiceSubscriptionService::class)->assignPlan($service, $addon);

        $resolved = app(EntitlementResolver::class)->resolveForService($service->fresh());

        $this->assertFalse($church->fresh()->hasCapability('exams'));
        $this->assertTrue((bool) $resolved['exams']);
        $this->assertTrue((bool) $resolved['curriculum']); // from church floor
    }

    public function test_church_capability_unchanged_when_only_service_addon_assigned(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $before = ChurchCapability::where('church_id', $church->church_id)
            ->where('capability_key', 'exams')
            ->value('enabled');

        $service = $this->createServiceForChurch($church);
        $addon = $this->createServiceOnlyPlan();
        app(ServiceSubscriptionService::class)->assignPlan($service, $addon);

        $after = ChurchCapability::where('church_id', $church->church_id)
            ->where('capability_key', 'exams')
            ->value('enabled');

        $this->assertSame((bool) $before, (bool) $after);
        $this->assertFalse($church->fresh()->hasCapability('exams'));
    }

    public function test_independent_payer_creates_service_billing_account(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $service = $this->createServiceForChurch($church);
        $addon = $this->createServiceOnlyPlan();

        $sub = app(ServiceSubscriptionService::class)->assignPlan(
            $service,
            $addon,
            null,
            'active',
            null,
            null,
            true
        );

        $this->assertTrue($sub->paysIndependently());
        $this->assertDatabaseHas('billing_account', [
            'service_id' => $service->service_id,
        ]);
        $account = BillingAccount::where('service_id', $service->service_id)->first();
        $this->assertNotNull($account->organization_id);
    }

    public function test_inherited_payer_leaves_billing_account_null(): void
    {
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'starter')->firstOrFail()
        );

        $service = $this->createServiceForChurch($church);
        $sub = app(ServiceSubscriptionService::class)->assignPlan(
            $service,
            $this->createServiceOnlyPlan(),
            null,
            'active',
            null,
            null,
            false
        );

        $this->assertNull($sub->billing_account_id);
        $this->assertFalse($sub->paysIndependently());
    }

    public function test_church_only_plan_rejected_for_service(): void
    {
        $church = $this->createChurch();
        $service = $this->createServiceForChurch($church);

        $plan = app(\App\Billing\SubscriptionPlanService::class)->create([
            'slug' => 'church-only-'.uniqid(),
            'name' => 'Church Only',
            'status' => 'active',
            'scope' => 'church',
            'includes_seats' => 10,
            'entitlements' => ['exams' => true],
        ]);

        $this->expectException(ValidationException::class);
        app(ServiceSubscriptionService::class)->assignPlan($service, $plan);
    }

    public function test_max_services_quota_blocks_extra_service(): void
    {
        $church = $this->createChurch();
        $pilot = SubscriptionPlan::where('slug', 'pilot')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $pilot);
        // pilot max_services = 1 after seeder update; ensure override
        app(ChurchSubscriptionService::class)->setOverride($church, 'max_services', 1);

        $this->createServiceForChurch($church);
        app(QuotaGuard::class)->syncServiceCount($church->fresh());

        $this->expectException(ValidationException::class);
        app(QuotaGuard::class)->enforce($church->fresh(), 'max_services', 1);
    }

    public function test_superadmin_can_open_service_billing_page(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'starter')->firstOrFail()
        );
        $service = $this->createServiceForChurch($church);

        $response = $this->actingAs($admin)->get(route('superadmin.services.billing', $service));

        $response->assertOk();
        $response->assertSee($service->localizedTitle());
    }

    public function test_superadmin_can_assign_service_plan_via_http(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'starter')->firstOrFail()
        );
        $service = $this->createServiceForChurch($church);
        $addon = $this->createServiceOnlyPlan();

        $response = $this->actingAs($admin)->post(route('superadmin.services.billing.assign', $service), [
            'plan_id' => $addon->plan_id,
            'status' => 'active',
            'independent_payer' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_subscription', [
            'service_id' => $service->service_id,
            'plan_id' => $addon->plan_id,
        ]);
    }

    public function test_non_superadmin_cannot_access_service_billing(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch();
        $service = $this->createServiceForChurch($church);

        $this->actingAs($user)->get(route('superadmin.services.billing', $service))->assertForbidden();
    }

    private function createServiceForChurch($church): ChurchService
    {
        $payload = [
            'title' => 'Billing Service '.uniqid(),
            'title_en' => 'Billing Service',
            'status' => ChurchService::STATUS_ACTIVE,
            'church_id' => $church->church_id,
            'permissions_version' => 0,
        ];

        if (Schema::hasColumn('service', 'structure_template_id') && class_exists(StructureTemplate::class)) {
            $template = StructureTemplate::query()->first();
            if ($template) {
                $payload['structure_template_id'] = $template->structure_template_id;
            }
        }

        return ChurchService::query()->withoutTenancy()->create($payload);
    }

    private function createServiceOnlyPlan(): SubscriptionPlan
    {
        return app(\App\Billing\SubscriptionPlanService::class)->create([
            'slug' => 'svc-addon-'.uniqid(),
            'name' => 'Svc Addon',
            'status' => 'active',
            'scope' => 'service',
            'includes_seats' => 1,
            'entitlements' => [
                'exams' => true,
                'live_quiz' => true,
            ],
        ]);
    }
}
