<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Billing\QuotaGuard;
use App\Models\ChurchUser;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\PlatformBillingSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class QuotaGuardTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_seat_limit_from_plan_is_enforced(): void
    {
        $church = $this->createChurch();
        $pilot = SubscriptionPlan::where('slug', 'pilot')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $pilot);

        $guard = app(QuotaGuard::class);
        $this->assertSame(50, $guard->limit($church, 'max_active_users'));
        $this->assertTrue($guard->canUse($church, 'max_active_users', 1));
    }

    public function test_enforce_throws_when_seat_quota_exceeded(): void
    {
        $church = $this->createChurch();
        $pilot = SubscriptionPlan::where('slug', 'pilot')->firstOrFail();
        $service = app(ChurchSubscriptionService::class);
        $service->assignPlan($church, $pilot);
        $service->setOverride($church, 'max_active_users', 1);

        $user = $this->createUser();
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        app(QuotaGuard::class)->syncSeatUsage($church->fresh());

        $this->expectException(ValidationException::class);
        app(QuotaGuard::class)->enforce($church->fresh(), 'max_active_users', 1);
    }

    public function test_storage_usage_can_be_recorded_and_checked(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $guard = app(QuotaGuard::class);
        $limit = $guard->limit($church, 'storage_bytes');
        $this->assertNotNull($limit);

        $this->assertTrue($guard->canUse($church, 'storage_bytes', 1024));
        $guard->recordUsage($church, 'storage_bytes', 1024);
        $this->assertSame(1024, $guard->used($church, 'storage_bytes'));
    }

    public function test_custom_domain_allowed_only_when_entitled(): void
    {
        $church = $this->createChurch();
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $starter);

        $guard = app(QuotaGuard::class);
        $this->assertFalse($guard->allowsCustomDomain($church));

        app(ChurchSubscriptionService::class)->setOverride($church, 'custom_domain', true);
        $this->assertTrue($guard->allowsCustomDomain($church->fresh()));
    }

    public function test_enterprise_plan_has_unlimited_storage_limit(): void
    {
        $church = $this->createChurch();
        $enterprise = SubscriptionPlan::where('slug', 'enterprise')->firstOrFail();
        app(ChurchSubscriptionService::class)->assignPlan($church, $enterprise);

        $this->assertNull(app(QuotaGuard::class)->limit($church, 'storage_bytes'));
        $this->assertTrue(app(QuotaGuard::class)->canUse($church, 'storage_bytes', 999_999_999_999));
    }
}
