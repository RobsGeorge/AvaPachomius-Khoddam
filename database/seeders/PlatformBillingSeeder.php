<?php

namespace Database\Seeders;

use App\Billing\PlatformFeatureCatalog;
use App\Billing\SubscriptionPlanService;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class PlatformBillingSeeder extends Seeder
{
    public function run(): void
    {
        app(PlatformFeatureCatalog::class)->syncFromConfig();

        if (SubscriptionPlan::query()->exists()) {
            $this->ensureServiceAddonPlan();

            return;
        }

        $planService = app(SubscriptionPlanService::class);

        $planService->create([
            'slug' => 'pilot',
            'name' => 'Pilot',
            'description' => 'Free pilot tier for evaluation churches.',
            'tier_rank' => 0,
            'is_public' => true,
            'status' => 'active',
            'scope' => 'both',
            'includes_seats' => 50,
            'entitlements' => [
                'curriculum' => true,
                'attendance' => true,
                'assignments' => true,
                'announcements' => true,
                'max_active_users' => 50,
                'max_services' => 1,
                'storage_bytes' => 2_147_483_648,
                'mobile_app' => 'none',
                'custom_domain' => false,
                'api_access' => false,
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 0, 'is_default' => true],
            ],
        ]);

        $planService->create([
            'slug' => 'starter',
            'name' => 'Starter',
            'description' => 'Core educational modules for small churches.',
            'tier_rank' => 10,
            'is_public' => true,
            'status' => 'active',
            'scope' => 'both',
            'includes_seats' => 150,
            'entitlements' => [
                'curriculum' => true,
                'attendance' => true,
                'assignments' => true,
                'grades' => true,
                'events' => true,
                'feedback' => true,
                'announcements' => true,
                'reporting' => true,
                'max_active_users' => 150,
                'max_services' => 3,
                'storage_bytes' => 10_737_418_240,
                'mobile_app' => 'student',
                'custom_domain' => false,
                'api_access' => false,
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 120_000, 'is_default' => true],
                ['billing_interval' => 'year', 'amount_minor' => 1_200_000],
            ],
        ]);

        $planService->create([
            'slug' => 'pro',
            'name' => 'Pro',
            'description' => 'Full educational suite plus church management.',
            'tier_rank' => 20,
            'is_public' => true,
            'status' => 'active',
            'scope' => 'both',
            'includes_seats' => 500,
            'entitlements' => [
                'curriculum' => true,
                'attendance' => true,
                'assignments' => true,
                'exams' => true,
                'grades' => true,
                'assessments' => true,
                'events' => true,
                'live_quiz' => true,
                'feedback' => true,
                'announcements' => true,
                'reporting' => true,
                'church_management' => true,
                'max_active_users' => 500,
                'max_services' => 10,
                'storage_bytes' => 53_687_091_200,
                'mobile_app' => 'student',
                'custom_domain' => true,
                'api_access' => false,
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 240_000, 'is_default' => true],
                ['billing_interval' => 'year', 'amount_minor' => 2_400_000],
            ],
        ]);

        $planService->create([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Unlimited scale, API access, and white-label.',
            'tier_rank' => 30,
            'is_public' => false,
            'status' => 'active',
            'scope' => 'both',
            'includes_seats' => 2000,
            'entitlements' => [
                'curriculum' => true,
                'attendance' => true,
                'assignments' => true,
                'exams' => true,
                'grades' => true,
                'assessments' => true,
                'events' => true,
                'live_quiz' => true,
                'feedback' => true,
                'announcements' => true,
                'reporting' => true,
                'church_management' => true,
                'max_active_users' => 2000,
                'max_services' => null,
                'storage_bytes' => null,
                'max_courses' => null,
                'mobile_app' => 'full',
                'custom_domain' => true,
                'api_access' => true,
                'white_label' => true,
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 600_000, 'is_default' => true],
            ],
        ]);

        $this->ensureServiceAddonPlan();
    }

    private function ensureServiceAddonPlan(): void
    {
        if (SubscriptionPlan::where('slug', 'service-addon')->exists()) {
            return;
        }

        app(SubscriptionPlanService::class)->create([
            'slug' => 'service-addon',
            'name' => 'Service Add-on',
            'description' => 'Upsell exams and live quiz for a single service on top of the church floor.',
            'tier_rank' => 15,
            'is_public' => true,
            'status' => 'active',
            'scope' => 'service',
            'includes_seats' => 1,
            'entitlements' => [
                'exams' => true,
                'live_quiz' => true,
                'grades' => true,
                'mobile_app' => 'student',
            ],
            'prices' => [
                ['billing_interval' => 'month', 'amount_minor' => 80_000, 'is_default' => true],
            ],
        ]);
    }
}
