<?php

namespace App\Console\Commands;

use App\Billing\ChurchSubscriptionService;
use App\Billing\PlatformFeatureCatalog;
use Illuminate\Console\Command;

class SyncBillingCatalogCommand extends Command
{
    protected $signature = 'billing:sync-catalog {--comp-tenant-zero : Ensure Tenant Zero has a comped subscription}';

    protected $description = 'Sync platform entitlement catalog from config and optionally comp Tenant Zero';

    public function handle(
        PlatformFeatureCatalog $catalog,
        ChurchSubscriptionService $subscriptions,
    ): int {
        $count = $catalog->syncFromConfig();
        $this->info("Synced {$count} platform features.");

        if ($this->option('comp-tenant-zero')) {
            $subscriptions->ensureTenantZeroComped();
            $this->info('Tenant Zero comped subscription ensured.');
        }

        return self::SUCCESS;
    }
}
