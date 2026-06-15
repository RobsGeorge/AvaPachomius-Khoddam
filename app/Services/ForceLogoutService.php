<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class ForceLogoutService
{
    /**
     * Invalidate every active session and clear remember-me tokens.
     *
     * @return array{sessions_cleared: int, remember_tokens_cleared: int, driver: string}
     */
    public static function logoutAllUsers(?string $exceptSessionId = null): array
    {
        $driver = (string) config('session.driver', 'file');

        $sessionsCleared = match ($driver) {
            'database' => self::flushDatabaseSessions($exceptSessionId),
            'redis'      => self::flushRedisSessions($exceptSessionId),
            'file'       => self::flushFileSessions($exceptSessionId),
            default      => self::flushUnsupportedDriver($driver),
        };

        $rememberTokensCleared = User::whereNotNull('remember_token')->update(['remember_token' => null]);

        return [
            'sessions_cleared'        => $sessionsCleared,
            'remember_tokens_cleared' => $rememberTokensCleared,
            'driver'                  => $driver,
        ];
    }

    private static function flushFileSessions(?string $exceptSessionId): int
    {
        $directory = config('session.files');

        if (! is_string($directory) || ! is_dir($directory)) {
            return 0;
        }

        $cleared = 0;

        foreach (File::files($directory) as $file) {
            $sessionId = $file->getFilename();

            if ($exceptSessionId !== null && hash_equals($exceptSessionId, $sessionId)) {
                continue;
            }

            if (@unlink($file->getPathname())) {
                $cleared++;
            }
        }

        return $cleared;
    }

    private static function flushDatabaseSessions(?string $exceptSessionId): int
    {
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            Log::warning('Force logout skipped database sessions: table missing', ['table' => $table]);

            return 0;
        }

        $query = DB::table($table);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    private static function flushRedisSessions(?string $exceptSessionId): int
    {
        try {
            $connectionName = config('session.connection');
            $redis = Redis::connection($connectionName);
            $cleared = 0;
            $cursor = null;

            do {
                $result = $redis->scan($cursor, ['match' => '*session*', 'count' => 100]);
                $cursor = is_array($result) ? ($result[0] ?? 0) : 0;
                $keys = is_array($result) ? ($result[1] ?? []) : [];

                foreach ($keys as $key) {
                    $sessionKey = (string) $key;

                    if ($exceptSessionId !== null && str_contains($sessionKey, $exceptSessionId)) {
                        continue;
                    }

                    if ($redis->del($sessionKey) > 0) {
                        $cleared++;
                    }
                }
            } while ($cursor !== 0 && $cursor !== '0');

            return $cleared;
        } catch (\Throwable $e) {
            Log::warning('Force logout redis flush failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    private static function flushUnsupportedDriver(string $driver): int
    {
        Log::warning('Force logout: session driver not supported for bulk flush', ['driver' => $driver]);

        return 0;
    }
}
