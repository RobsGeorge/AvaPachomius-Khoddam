<?php

namespace App\Tenancy;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the request → church and binds it for the request lifetime.
 *
 * Dormant while tenancy is disabled (MULTI_TENANT=false, i.e. production until the T7
 * cutover): no church is bound, so the global scope no-ops and behavior is unchanged.
 * When enabled, every host resolves to a church (real subdomain resolution lands in
 * T4; until then everything falls back to the main church). The cross-church superadmin
 * console host is intentionally left unbound (superadmin-gated at the route level).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('tenancy.enabled')) {
            return $next($request);
        }

        $host = $request->getHost();

        if ($host === config('tenancy.console_host')) {
            return $next($request); // superadmin console — no church binding
        }

        $church = Church::where('domain', $host)->first()              // custom domain (T4)
            ?? Church::where('slug', explode('.', $host)[0])->first()  // subdomain (T4)
            ?? Church::main();                                          // default fallback

        abort_if(! $church || $church->status !== 'active', 404, 'Unknown church.');

        TenantContext::set($church);
        view()->share('currentChurch', $church);

        return $next($request);
    }
}
