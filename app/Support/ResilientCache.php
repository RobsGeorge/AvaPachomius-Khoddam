<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-through cache for values that can always be recomputed from the database.
 *
 * A cache directory the runtime user cannot write (deploy vs www-data ownership on
 * the VPS) must only cost performance. Callers here would otherwise fail the whole
 * request over a cache *write*, which took the site down for anyone whose cache key
 * hashed into a broken shard directory.
 */
final class ResilientCache
{
    /** @var array<string, true> */
    private static array $reported = [];

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        try {
            return Cache::remember($key, $ttlSeconds, $callback);
        } catch (Throwable $e) {
            if (! StoragePermissionError::matches($e)) {
                throw $e;
            }

            self::reportOnce($key, $e);

            return $callback();
        }
    }

    public static function forgetReportedKeys(): void
    {
        self::$reported = [];
    }

    private static function reportOnce(string $key, Throwable $e): void
    {
        if (isset(self::$reported[$key])) {
            return;
        }

        self::$reported[$key] = true;

        Log::warning('Cache unavailable; serving uncached value.', [
            'key' => $key,
            'reason' => $e->getMessage(),
        ]);
    }
}
