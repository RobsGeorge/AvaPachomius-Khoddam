<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Billing\EntitlementResolver;
use App\Billing\EntitlementSyncService;
use App\Billing\PlatformFeatureCatalog;
use App\Billing\QuotaGuard;
use App\Billing\SubscriptionPlanService;
use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchEntitlementSnapshot;
use App\Models\ChurchSubscription;
use App\Models\SubscriptionPlan;
use Database\Seeders\PlatformBillingSeeder;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class EntitlementResolverTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_plan_entitlements_resolve_for_church(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $resolver = app(EntitlementResolver::class);
        $features = $resolver->resolve($church->fresh());

        $this->assertTrue($features['curriculum']);
        $this->assertFalse($features['exams']);
        $this->assertSame(150, $features['max_active_users']);
        $this->assertSame('student', $features['mobile_app']);
    }

    public function test_override_takes_precedence_over_plan(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service = app(ChurchSubscriptionService::class);
        $service->assignPlan($church, $starter);
        $service->setOverride($church, 'exams', true, null, 'promo');

        $features = app(EntitlementResolver::class)->resolve($church->fresh());

        $this->assertTrue($features['exams']);
    }

    public function test_expired_override_is_ignored(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service = app(ChurchSubscriptionService::class);
        $service->assignPlan($church, $starter);
        $service->setOverride($church, 'exams', true, null, 'temp', new \DateTimeImmutable('-1 day'));

        $features = app(EntitlementResolver::class)->resolve($church->fresh(), true);

        $this->assertFalse($features['exams']);
    }

    public function test_snapshot_is_persisted_on_resolve(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        app(EntitlementResolver::class)->resolve($church->fresh());

        $this->assertDatabaseHas('church_entitlement_snapshot', [
            'church_id' => $church->church_id,
        ]);

        $snapshot = ChurchEntitlementSnapshot::find($church->church_id);
        $this->assertIsArray($snapshot->features);
        $this->assertArrayHasKey('curriculum', $snapshot->features);
    }

    public function test_platform_feature_catalog_syncs_from_config(): void
    {
        $count = app(PlatformFeatureCatalog::class)->syncFromConfig();

        $this->assertGreaterThan(10, $count);
        $this->assertDatabaseHas('platform_feature', ['feature_key' => 'exams', 'type' => 'boolean']);
        $this->assertDatabaseHas('platform_feature', ['feature_key' => 'storage_bytes', 'type' => 'limit']);
    }
}
