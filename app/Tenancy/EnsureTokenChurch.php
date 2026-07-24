<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Binds an API bearer token to the church it was issued for. At login the token is given
 * a `church:{slug}` ability (see AuthController) — the same convention ResolveTenant reads
 * to pin an API request to the token's church. That pinning already closes the host vector
 * (a church-A token can't bind church B just by hitting B's host); this middleware closes
 * the *override* vector: the X-Church-Slug / X-Tenant-Slug / church_slug / custom-domain
 * inputs outrank the token claim in ResolveTenant, so a token could otherwise be pointed at
 * a church it wasn't issued for. Reject when the bound church's slug isn't in the token's
 * abilities — defense in depth beyond the membership gate, since a user who belongs to
 * *both* churches would otherwise pass membership with either church's token.
 *
 * Runs after auth:sanctum (needs the resolved token). Dormant while tenancy is disabled,
 * and a no-op for wildcard/legacy tokens (which `can()` any ability). Alias: `token.church`.
 */
class EnsureTokenChurch
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('tenancy.enabled')) {
            return $next($request);
        }

        $church = TenantContext::current();
        $user = $request->user();

        if (! $church || ! $user || ($user->is_superadmin ?? false)) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can("church:{$church->slug}")) {
            abort(403, __('auth.token_wrong_church'));
        }

        return $next($request);
    }
}
