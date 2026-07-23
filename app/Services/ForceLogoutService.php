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

    /**
     * Invalidate sessions, remember-me tokens, and permission cache for specific users.
     *
     * @param  list<int|string>  $userIds
     * @return array{
     *     sessions_cleared: int,
     *     remember_tokens_cleared: int,
     *     users_targeted: int,
     *     driver: string
     * }
     */
    public static function logoutUsers(array $userIds, ?string $exceptSessionId = null): array
    {
        $normalizedUserIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedUserIds === []) {
            return [
                'sessions_cleared'        => 0,
                'remember_tokens_cleared' => 0,
                'users_targeted'          => 0,
                'driver'                  => (string) config('session.driver', 'file'),
            ];
        }

        $driver = (string) config('session.driver', 'file');

        $sessionsCleared = match ($driver) {
            'database' => self::flushDatabaseSessionsForUsers($normalizedUserIds, $exceptSessionId),
            'redis'      => self::flushRedisSessionsForUsers($normalizedUserIds, $exceptSessionId),
            'file'       => self::flushFileSessionsForUsers($normalizedUserIds, $exceptSessionId),
            default      => self::flushUnsupportedDriver($driver),
        };

        $rememberTokensCleared = User::query()
            ->whereIn('user_id', $normalizedUserIds)
            ->whereNotNull('remember_token')
            ->update(['remember_token' => null]);

        $resolver = app(CoursePermissionResolver::class);
        foreach (User::whereIn('user_id', $normalizedUserIds)->get() as $user) {
            $resolver->clearUserCache($user);
        }

        return [
            'sessions_cleared'        => $sessionsCleared,
            'remember_tokens_cleared' => $rememberTokensCleared,
            'users_targeted'          => count($normalizedUserIds),
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

    /**
     * @param  list<int>  $userIds
     */
    private static function flushFileSessionsForUsers(array $userIds, ?string $exceptSessionId): int
    {
        $directory = config('session.files');

        if (! is_string($directory) || ! is_dir($directory)) {
            return 0;
        }

        $userIdSet = array_fill_keys($userIds, true);
        $cleared = 0;

        foreach (File::files($directory) as $file) {
            $sessionId = $file->getFilename();

            if ($exceptSessionId !== null && hash_equals($exceptSessionId, $sessionId)) {
                continue;
            }

            $payload = @file_get_contents($file->getPathname());
            if ($payload === false) {
                continue;
            }

            $sessionUserId = self::extractUserIdFromSerializedPayload($payload);
            if ($sessionUserId === null || ! isset($userIdSet[$sessionUserId])) {
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

    /**
     * @param  list<int>  $userIds
     */
    private static function flushDatabaseSessionsForUsers(array $userIds, ?string $exceptSessionId): int
    {
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            Log::warning('Force logout skipped database sessions: table missing', ['table' => $table]);

            return 0;
        }

        $query = DB::table($table)->whereIn('user_id', $userIds);

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

    /**
     * @param  list<int>  $userIds
     */
    private static function flushRedisSessionsForUsers(array $userIds, ?string $exceptSessionId): int
    {
        try {
            $connectionName = config('session.connection');
            $redis = Redis::connection($connectionName);
            $userIdSet = array_fill_keys($userIds, true);
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

                    $payload = $redis->get($sessionKey);
                    if (! is_string($payload)) {
                        continue;
                    }

                    $sessionUserId = self::extractUserIdFromSerializedPayload($payload);
                    if ($sessionUserId === null || ! isset($userIdSet[$sessionUserId])) {
                        continue;
                    }

                    if ($redis->del($sessionKey) > 0) {
                        $cleared++;
                    }
                }
            } while ($cursor !== 0 && $cursor !== '0');

            return $cleared;
        } catch (\Throwable $e) {
            Log::warning('Force logout redis selective flush failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    private static function flushUnsupportedDriver(string $driver): int
    {
        Log::warning('Force logout: session driver not supported for bulk flush', ['driver' => $driver]);

        return 0;
    }

    private static function extractUserIdFromSerializedPayload(string $payload): ?int
    {
        $decoded = self::decodeSessionPayload($payload);
        if (! is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'login_web_') && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeSessionPayload(string $payload): ?array
    {
        $attempts = [$payload];

        $base64Decoded = base64_decode($payload, true);
        if ($base64Decoded !== false) {
            $attempts[] = $base64Decoded;
        }

        foreach ($attempts as $candidate) {
            $data = @unserialize($candidate);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }
}
