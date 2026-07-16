<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Http\Request;

/**
 * T2 — gates a route on a per-church capability. A church without the capability gets a
 * 404 (the feature does not exist there). Dormant when no church is bound (tenancy
 * disabled, i.e. production until the T7 cutover): all features are available, so
 * behavior is unchanged. Alias: `capability`.
 */
class RequireCapability
{
    public function handle(Request $request, Closure $next, string $key)
    {
        $church = TenantContext::current();

        if ($church === null) {
            return $next($request); // tenancy dormant → every feature available
        }

        abort_unless($church->hasCapability($key), 404);

        return $next($request);
    }
}
