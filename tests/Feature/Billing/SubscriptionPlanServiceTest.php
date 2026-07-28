<?php

namespace Tests\Feature\Billing;

use App\Billing\SubscriptionPlanService;
use App\Models\PlanEntitlement;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use Database\Seeders\PlatformBillingSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class SubscriptionPlanServiceTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingCatalog();
    }

    public function test_create_plan_with_entitlements_and_prices(): void
    {
        $plan = app(SubscriptionPlanService::class)->create([
            'slug' => 'test-tier',
            'name' => 'Test Tier',
            'status' => 'active',
            'includes_seats' => 99,
            'entitlements' => [
                'curriculum' => true,
                'exams' => false,
                'max_active_users' => 99,
                'mobile_app' => 'student',
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 50_000, 'is_default' => true],
            ],
        ]);

        $this->assertDatabaseHas('subscription_plan', ['slug' => 'test-tier']);
        $this->assertTrue(
            PlanEntitlement::where('plan_id', $plan->plan_id)->where('feature_key', 'curriculum')->exists()
        );
        $this->assertTrue(
            PlanPrice::where('plan_id', $plan->plan_id)->where('billing_interval', 'month')->exists()
        );
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        app(SubscriptionPlanService::class)->create([
            'slug' => 'dup-plan',
            'name' => 'Dup',
            'status' => 'draft',
        ]);

        $this->expectException(ValidationException::class);
        app(SubscriptionPlanService::class)->create([
            'slug' => 'dup-plan',
            'name' => 'Dup 2',
            'status' => 'draft',
        ]);
    }

    public function test_update_plan_syncs_entitlements(): void
    {
        $plan = app(SubscriptionPlanService::class)->create([
            'slug' => 'mutable',
            'name' => 'Mutable',
            'status' => 'active',
            'entitlements' => ['exams' => false],
        ]);

        app(SubscriptionPlanService::class)->update($plan, [
            'entitlements' => ['exams' => true],
        ]);

        $ent = PlanEntitlement::where('plan_id', $plan->plan_id)->where('feature_key', 'exams')->first();
        $this->assertTrue($ent->resolvedValue());
    }

    public function test_seeder_creates_default_plans(): void
    {
        $this->seed(PlatformBillingSeeder::class);

        $this->assertSame(5, SubscriptionPlan::count());
        $this->assertTrue(SubscriptionPlan::where('slug', 'enterprise')->exists());
        $this->assertTrue(SubscriptionPlan::where('slug', 'service-addon')->exists());
    }
}
