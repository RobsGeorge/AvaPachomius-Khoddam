<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\ChurchStorageQuotaService;
use Illuminate\Console\Command;

class ReconcileChurchStorageUsage extends Command
{
    protected $signature = 'church:reconcile-storage {church_id? : Optional church_id to reconcile}';

    protected $description = 'Reconcile curriculum storage_used_bytes in church settings from media_assets';

    public function handle(ChurchStorageQuotaService $quota): int
    {
        $churchId = $this->argument('church_id');

        $query = Church::query()->orderBy('church_id');
        if ($churchId) {
            $query->where('church_id', $churchId);
        }

        $count = 0;
        foreach ($query->cursor() as $church) {
            $before = $quota->usedBytes($church);
            $after = $quota->reconcileUsedBytes($church);
            $this->line("Church {$church->church_id} ({$church->slug}): {$before} → {$after} bytes");
            $count++;
        }

        $this->info("Reconciled {$count} church(es).");

        return self::SUCCESS;
    }
}
