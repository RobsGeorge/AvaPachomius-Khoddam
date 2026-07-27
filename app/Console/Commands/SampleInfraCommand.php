<?php

namespace App\Console\Commands;

use App\Models\InfraSample;
use App\Observability\Contracts\InfraMetricsAdapter;
use Illuminate\Console\Command;

class SampleInfraCommand extends Command
{
    protected $signature = 'observability:sample-infra';

    protected $description = 'Sample host metrics via the configured InfraMetricsAdapter';

    public function handle(InfraMetricsAdapter $adapter): int
    {
        if (! config('observability.enabled', true)) {
            $this->info('Observability disabled.');

            return self::SUCCESS;
        }

        $sample = $adapter->sample();
        if ($sample === null) {
            $this->warn('Infra adapter returned no sample.');

            return self::SUCCESS;
        }

        InfraSample::query()->create([
            'sampled_at' => now(),
            'host' => $sample['host'],
            'load_1' => $sample['load_1'] ?? null,
            'load_5' => $sample['load_5'] ?? null,
            'cpu_pct' => $sample['cpu_pct'] ?? null,
            'mem_used_mb' => $sample['mem_used_mb'] ?? null,
            'mem_total_mb' => $sample['mem_total_mb'] ?? null,
            'disk_used_pct' => $sample['disk_used_pct'] ?? null,
            'php_fpm_active' => $sample['php_fpm_active'] ?? null,
            'source' => $sample['source'] ?? 'unknown',
        ]);

        $this->info('Infra sample stored for '.$sample['host']);

        return self::SUCCESS;
    }
}
