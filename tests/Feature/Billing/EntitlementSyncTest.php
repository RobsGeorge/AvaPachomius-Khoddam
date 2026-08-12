<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Billing\EntitlementSyncService;
use App\Models\ChurchCapability;
use App\Models\SubscriptionPlan;
use Database\Seeders\PlatformBillingSeeder;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class EntitlementSyncTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_starter_plan_syncs_capabilities_to_church(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);
        app(EntitlementSyncService::class)->sync($church->fresh());

        $church = $church->fresh();
        $this->assertTrue($church->hasCapability('curriculum'));
        $this->assertTrue($church->hasCapability('grades'));
        $this->assertFalse($church->hasCapability('exams'));
        $this->assertFalse($church->hasCapability('church_management'));
    }

    public function test_pro_plan_enables_church_management(): void
    {
        $church = $this->createChurch();
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();

        app(ChurchSubscriptionService::class)->assignPlan($church, $pro);

        $church = $church->fresh();
        $this->assertTrue($church->hasCapability('exams'));
        $this->assertTrue($church->hasCapability('church_management'));
    }

    public function test_override_enables_capability_via_sync(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service = app(ChurchSubscriptionService::class);
        $service->assignPlan($church, $starter);
        $service->setOverride($church, 'exams', true);

        $this->assertTrue($church->fresh()->hasCapability('exams'));
        $this->assertTrue(
            ChurchCapability::where('church_id', $church->church_id)
                ->where('capability_key', 'exams')
                ->where('enabled', true)
                ->exists()
        );
    }

    public function test_church_without_subscription_is_not_subscription_managed(): void
    {
        $church = $this->createChurch();

        $this->assertFalse($church->isSubscriptionManaged());
    }

    public function test_comped_subscription_is_subscription_managed(): void
    {
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->compChurch($church);

        $this->assertTrue($church->fresh()->isSubscriptionManaged());
    }
}
