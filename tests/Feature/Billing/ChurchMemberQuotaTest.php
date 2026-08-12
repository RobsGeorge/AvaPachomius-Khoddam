<?php

namespace Tests\Feature\Billing;

use App\Billing\ChurchSubscriptionService;
use App\Models\ChurchUser;
use App\Models\SubscriptionPlan;
use Database\Seeders\PlatformBillingSeeder;
use Tests\Support\BillingTestHelpers;
use Tests\Support\EventModuleTestCase;

class ChurchMemberQuotaTest extends EventModuleTestCase
{
    use BillingTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_add_member_blocked_when_seat_quota_full(): void
    {
        $admin = $this->createSuperadmin();
        $church = $this->createChurch();
        $service = app(ChurchSubscriptionService::class);
        $service->assignPlan($church, SubscriptionPlan::where('slug', 'pilot')->firstOrFail());
        $service->setOverride($church, 'max_active_users', 1);

        $existing = $this->createUser(['email' => 'member1@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $existing->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $newUser = $this->createUser(['email' => 'member2@example.com']);

        $response = $this->actingAs($admin)->post(route('superadmin.churches.members.store', $church), [
            'email' => $newUser->email,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(
            ChurchUser::where('church_id', $church->church_id)->where('user_id', $newUser->user_id)->exists()
        );
    }
}
