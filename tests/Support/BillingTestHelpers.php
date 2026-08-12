<?php

namespace Tests\Support;

use App\Billing\PlatformFeatureCatalog;
use Database\Seeders\PlatformBillingSeeder;
use Illuminate\Support\Facades\Artisan;

trait BillingTestHelpers
{
    protected function seedBillingCatalog(): void
    {
        app(PlatformFeatureCatalog::class)->syncFromConfig();
    }

    protected function seedBillingPlans(): void
    {
        $this->seedBillingCatalog();
        $this->seed(PlatformBillingSeeder::class);
    }

    protected function createSuperadmin(array $overrides = []): \App\Models\User
    {
        return $this->createUser(array_merge([
            'is_superadmin' => true,
            'email' => 'billing-super-'.uniqid().'@example.com',
        ], $overrides));
    }
}
