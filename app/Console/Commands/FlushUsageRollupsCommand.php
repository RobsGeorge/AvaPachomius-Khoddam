<?php

namespace App\Console\Commands;

use App\Models\UsageRollup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FlushUsageRollupsCommand extends Command
{
    protected $signature = 'observability:flush-usage {--minutes=120 : Look back window for cache keys}';

    protected $description = 'Flush cached usage counters into usage_rollups';

    public function handle(): int
    {
        $minutes = max(5, (int) $this->option('minutes'));
        $bucketMinutes = max(1, (int) config('observability.usage_bucket_minutes', 5));
        $flushed = 0;

        $cursor = now()->startOfMinute()->subMinutes($minutes);
        $end = now()->startOfMinute();

        while ($cursor <= $end) {
            $aligned = $cursor->copy()->subMinutes($cursor->minute % $bucketMinutes);
            // Scan a modest church/service id space via known cache increments is hard without
            // Redis SCAN; instead persist any keys discovered via a registry set.
            $registryKey = 'obs:usage:registry:'.$aligned->format('YmdHi');
            $entries = Cache::get($registryKey, []);
            if (! is_array($entries)) {
                $entries = [];
            }

            foreach ($entries as $entry) {
                [$churchId, $serviceId] = array_pad(explode(':', (string) $entry), 2, '0');
                $churchId = (int) $churchId;
                $serviceId = (int) $serviceId;
                $baseKey = sprintf('obs:usage:%s:%d:%d', $aligned->format('YmdHi'), $churchId, $serviceId);
                $requests = (int) Cache::pull($baseKey.':requests', 0);
                $users = Cache::pull($baseKey.':users', []);
                $sessions = Cache::pull($baseKey.':sessions', []);
                if ($requests <= 0 && empty($users) && empty($sessions)) {
                    continue;
                }

                $this->upsertRollup($aligned, $churchId ?: null, $serviceId ?: null, $requests, $users, $sessions);
                $flushed++;
            }

            Cache::forget($registryKey);
            $cursor->addMinutes($bucketMinutes);
        }

        $this->info("Flushed {$flushed} usage rollup buckets.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $users
     * @param  array<string, mixed>  $sessions
     */
    private function upsertRollup(Carbon $bucket, ?int $churchId, ?int $serviceId, int $requests, array $users, array $sessions): void
    {
        $existing = UsageRollup::withoutTenancy()
            ->where('bucket_start', $bucket)
            ->where('church_id', $churchId)
            ->where('service_id', $serviceId)
            ->first();

        $activeUsers = is_array($users) ? count($users) : 0;
        $uniqueSessions = is_array($sessions) ? count($sessions) : 0;

        if ($existing) {
            $existing->update([
                'request_count' => max($existing->request_count, $requests),
                'active_users' => max($existing->active_users, $activeUsers),
                'unique_sessions' => max($existing->unique_sessions, $uniqueSessions),
            ]);

            return;
        }

        UsageRollup::withoutTenancy()->create([
            'bucket_start' => $bucket,
            'church_id' => $churchId,
            'service_id' => $serviceId,
            'request_count' => $requests,
            'active_users' => $activeUsers,
            'unique_sessions' => $uniqueSessions,
        ]);
    }
}
