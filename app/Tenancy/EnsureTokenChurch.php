<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Binds an API bearer token to the church it was issued for. At login the token is given
 * a `church:{id}` ability (see AuthController); this middleware rejects replaying one
 * church's token against another church's host — defense in depth beyond the membership
 * gate, since a user who belongs to *both* churches would otherwise pass membership with
 * either church's token.
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

        if ($token instanceof PersonalAccessToken && ! $token->can("church:{$church->church_id}")) {
            abort(403, __('auth.token_wrong_church'));
        }

        return $next($request);
    }
}
