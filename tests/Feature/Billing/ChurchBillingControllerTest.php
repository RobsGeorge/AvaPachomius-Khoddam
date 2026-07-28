<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Models\ChurchSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\PlatformBillingSeeder;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class ChurchBillingControllerTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_superadmin_can_view_plans_index(): void
    {
        $admin = $this->createSuperadmin();

        $response = $this->actingAs($admin)->get(route('superadmin.plans.index'));

        $response->assertOk();
        $response->assertSee('Starter');
        $response->assertSee('Pro');
    }

    public function test_superadmin_can_create_plan(): void
    {
        $admin = $this->createSuperadmin();

        $response = $this->actingAs($admin)->post(route('superadmin.plans.store'), [
            'slug' => 'custom-offer',
            'name' => 'Custom Offer',
            'status' => 'active',
            'includes_seats' => 200,
            'seat_overage_policy' => 'block',
            'entitlements' => [
                'curriculum' => '1',
                'exams' => '1',
                'max_active_users' => 200,
                'mobile_app' => 'full',
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 300_000, 'currency' => 'EGP', 'is_default' => '1'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subscription_plan', ['slug' => 'custom-offer']);
    }

    public function test_superadmin_can_assign_plan_to_church(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('superadmin.churches.billing.assign', $church), [
            'plan_id' => $pro->plan_id,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('church_subscription', [
            'church_id' => $church->church_id,
            'plan_id' => $pro->plan_id,
            'status' => 'active',
        ]);
        $this->assertTrue($church->fresh()->hasCapability('exams'));
    }

    public function test_superadmin_can_view_church_billing_page(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'starter')->firstOrFail()
        );

        $response = $this->actingAs($admin)->get(route('superadmin.churches.billing', $church));

        $response->assertOk();
        $response->assertSee('Starter');
    }

    public function test_superadmin_can_add_entitlement_override(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'pilot')->firstOrFail()
        );

        $response = $this->actingAs($admin)->post(route('superadmin.churches.billing.overrides.store', $church), [
            'feature_key' => 'api_access',
            'value' => '1',
            'reason' => 'trial extension',
        ]);

        $response->assertRedirect();
        $this->assertTrue($church->fresh()->hasEntitlement('api_access'));
    }

    public function test_non_superadmin_cannot_access_plans(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('superadmin.plans.index'))->assertForbidden();
    }

    public function test_custom_domain_update_blocked_without_entitlement(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        app(ChurchSubscriptionService::class)->assignPlan(
            $church,
            SubscriptionPlan::where('slug', 'starter')->firstOrFail()
        );

        $response = $this->actingAs($admin)->put(route('superadmin.churches.update', $church), [
            'name' => $church->name,
            'domain' => 'custom.example.com',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('domain');
    }

    public function test_tenant_zero_comp_creates_subscription(): void
    {
        app(ChurchSubscriptionService::class)->ensureTenantZeroComped();

        $main = \App\Models\Church::main();
        $this->assertTrue(
            ChurchSubscription::where('church_id', $main->church_id)->where('status', 'comped')->exists()
        );
    }
}
