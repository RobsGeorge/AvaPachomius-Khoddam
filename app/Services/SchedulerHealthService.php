<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class SchedulerHealthService
{
    public const CACHE_KEY = 'scheduler:last_heartbeat_at';

    public function recordHeartbeat(): void
    {
        Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }

    public function lastHeartbeatAt(): ?CarbonInterface
    {
        $raw = Cache::get(self::CACHE_KEY);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{healthy: bool, last_heartbeat_at: ?CarbonInterface, stale_after_minutes: int, minutes_since: ?int} */
    public function status(): array
    {
        $staleAfter = max(1, (int) config('scheduled_tasks.health.stale_after_minutes', 5));
        $last = $this->lastHeartbeatAt();
        $minutesSince = $last ? (int) $last->diffInMinutes(now()) : null;
        $healthy = $last !== null && $minutesSince !== null && $minutesSince < $staleAfter;

        return [
            'healthy' => $healthy,
            'last_heartbeat_at' => $last,
            'stale_after_minutes' => $staleAfter,
            'minutes_since' => $minutesSince,
        ];
    }

    public function isHealthy(): bool
    {
        return $this->status()['healthy'];
    }
}
