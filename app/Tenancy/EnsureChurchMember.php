<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Http\Request;

/**
 * Rejects an authenticated user who is not a member of the currently-bound church
 * (superadmin exempt). No-op when no church is bound (tenancy disabled) — so it is
 * transparent in production until the T7 cutover. Alias: `church.member`.
 */
class EnsureChurchMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $church = TenantContext::current();

        if ($user && $church && ! ($user->is_superadmin ?? false)
            && ! $user->belongsToChurch($church->church_id)) {
            abort(403, __('auth.not_a_church_member'));
        }

        return $next($request);
    }
}
