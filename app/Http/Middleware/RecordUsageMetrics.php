<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Increments per-bucket usage counters in cache for later rollup flush.
 */
class RecordUsageMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('observability.enabled', true)) {
            return $response;
        }

        try {
            $minutes = max(1, (int) config('observability.usage_bucket_minutes', 5));
            $bucket = now()->copy()->startOfMinute();
            $bucket->subMinutes($bucket->minute % $minutes);

            $churchId = (int) (TenantContext::id() ?? 0);
            $serviceId = 0;
            if (function_exists('current_service') && current_service()) {
                $serviceId = (int) (current_service()->service_id ?? 0);
            }

            $stamp = $bucket->format('YmdHi');
            $baseKey = sprintf('obs:usage:%s:%d:%d', $stamp, $churchId, $serviceId);
            Cache::increment($baseKey.':requests');

            $registryKey = 'obs:usage:registry:'.$stamp;
            $registry = Cache::get($registryKey, []);
            if (! is_array($registry)) {
                $registry = [];
            }
            $registry[$churchId.':'.$serviceId] = $churchId.':'.$serviceId;
            Cache::put($registryKey, $registry, now()->addHours(3));

            if (Auth::check()) {
                $userKey = $baseKey.':users';
                $users = Cache::get($userKey, []);
                if (! is_array($users)) {
                    $users = [];
                }
                $users[(string) Auth::id()] = true;
                Cache::put($userKey, $users, now()->addHours(3));
            }

            if ($request->hasSession()) {
                $sessionKey = $baseKey.':sessions';
                $sessions = Cache::get($sessionKey, []);
                if (! is_array($sessions)) {
                    $sessions = [];
                }
                $sessions[hash('sha256', (string) $request->session()->getId())] = true;
                Cache::put($sessionKey, $sessions, now()->addHours(3));
            }
        } catch (\Throwable) {
            // Never break requests for metrics.
        }

        return $response;
    }
}
