<?php

namespace App\Console\Commands;

use App\Models\InfraSample;
use App\Models\ObservabilityEvent;
use App\Models\UsageRollup;
use Illuminate\Console\Command;

class PruneObservabilityCommand extends Command
{
    protected $signature = 'observability:prune';

    protected $description = 'Prune old observability_events, infra_samples, and usage_rollups by retention config';

    public function handle(): int
    {
        $eventsDays = max(1, (int) config('observability.retention.events_days', 90));
        $infraDays = max(1, (int) config('observability.retention.infra_days', 90));
        $rollupsDays = max(1, (int) config('observability.retention.rollups_days', 730));

        $eventsDeleted = ObservabilityEvent::withoutTenancy()
            ->where('occurred_at', '<', now()->subDays($eventsDays))
            ->delete();

        $infraDeleted = InfraSample::query()
            ->where('sampled_at', '<', now()->subDays($infraDays))
            ->delete();

        $rollupsDeleted = UsageRollup::withoutTenancy()
            ->where('bucket_start', '<', now()->subDays($rollupsDays))
            ->delete();

        $this->info("Pruned events={$eventsDeleted} infra={$infraDeleted} rollups={$rollupsDeleted}");

        return self::SUCCESS;
    }
}
