<?php

namespace App\Tenancy;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolve request → church and bind TenantContext for the request lifetime.
 *
 * - MULTI_TENANT=false → always Tenant Zero (church 1). Read scope filters to that
 *   church; since all production data is church_id=1, behavior matches pre-tenancy.
 * - MULTI_TENANT=true  → web: subdomain / custom domain; api: token claim / header /
 *   Host. Unknown tenant → 404. Console host stays unbound (superadmin).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('tenancy.enabled')) {
            try {
                $main = Church::main();
                TenantContext::set($main);
                view()->share('currentChurch', $main);
            } catch (\Throwable $e) {
                report($e);
                TenantContext::clear();
                view()->share('currentChurch', null);
            }

            return $next($request);
        }

        $host = $request->getHost();

        if ($host === config('tenancy.console_host')) {
            TenantContext::clear();

            return $next($request);
        }

        $church = $this->resolveChurch($request, $host);

        abort_if(! $church || $church->status !== 'active', 404, 'Unknown church.');

        TenantContext::set($church);
        view()->share('currentChurch', $church);

        return $next($request);
    }

    private function resolveChurch(Request $request, string $host): ?Church
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $fromClaim = $this->churchFromApiClaim($request);
            if ($fromClaim) {
                return $fromClaim;
            }
        }

        $byDomain = Church::query()->where('domain', $host)->first();
        if ($byDomain) {
            return $byDomain;
        }

        $slug = explode('.', $host)[0] ?? null;
        if ($slug && $slug !== $host) {
            $bySlug = Church::query()->where('slug', $slug)->first();
            if ($bySlug) {
                return $bySlug;
            }
        }

        // Apex / APP_URL host (no tenant subdomain) → Tenant Zero.
        if ($this->isApexHost($host)) {
            return Church::main();
        }

        // Single-label hosts (e.g. "localhost") with a matching slug.
        if ($slug) {
            return Church::query()->where('slug', $slug)->first();
        }

        return null;
    }

    private function churchFromApiClaim(Request $request): ?Church
    {
        $slug = $request->header('X-Church-Slug')
            ?? $request->header('X-Tenant-Slug')
            ?? $request->input('church_slug');

        if (! $slug && $request->user()) {
            $token = $request->user()->currentAccessToken();
            if ($token && is_iterable($token->abilities ?? null)) {
                foreach ($token->abilities as $ability) {
                    if (is_string($ability) && str_starts_with($ability, 'church:')) {
                        $slug = substr($ability, strlen('church:'));
                        break;
                    }
                }
            }
        }

        if (! $slug) {
            return null;
        }

        return Church::query()->where('slug', $slug)->first();
    }

    private function isApexHost(string $host): bool
    {
        $base = config('tenancy.base_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! $base) {
            return in_array($host, ['localhost', '127.0.0.1'], true);
        }

        return strcasecmp($host, $base) === 0
            || strcasecmp($host, 'www.'.$base) === 0;
    }
}
