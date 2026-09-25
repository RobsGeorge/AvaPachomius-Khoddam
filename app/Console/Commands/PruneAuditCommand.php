<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\LoginTrial;
use Illuminate\Console\Command;

class PruneAuditCommand extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Prune old activity_logs and login_trials by retention config';

    public function handle(): int
    {
        $activityDays = max(1, (int) config('audit.retention.activity_logs_days', 90));
        $trialDays = max(1, (int) config('audit.retention.login_trials_days', 90));

        // Cross-tenant prune: activity_logs are church-scoped; login_trials are platform-wide.
        $activityDeleted = ActivityLog::withoutTenancy()
            ->where('created_at', '<', now()->subDays($activityDays))
            ->delete();

        $trialsDeleted = LoginTrial::query()
            ->where('created_at', '<', now()->subDays($trialDays))
            ->delete();

        $this->info("Pruned activity_logs={$activityDeleted} login_trials={$trialsDeleted}");

        return self::SUCCESS;
    }
}
