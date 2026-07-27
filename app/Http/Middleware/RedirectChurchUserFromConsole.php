<?php

namespace App\Http\Middleware;

use App\Support\ChurchHost;
use App\Support\SuperadminWorkspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Multi-tenant only: church members (admin, priest, servant, …) must use their
 * church host. The console host (admin.*) is for platform superadmins only.
 */
class RedirectChurchUserFromConsole
{
    /** @var list<string> */
    private const EXEMPT_PREFIXES = [
        '/login',
        '/logout',
        '/locale/',
        '/theme',
        '/password',
        '/register',
        '/email/',
        '/otp',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! config('tenancy.enabled') || ! ChurchHost::isConsoleHost()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || SuperadminWorkspace::isSuperadmin($user)) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/')
                || str_starts_with($path, rtrim($prefix, '/').'/')
                || str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        $church = $user->churches()
            ->where('church.status', 'active')
            ->wherePivot('status', 'active')
            ->orderBy('church.name')
            ->first();

        if (! $church) {
            abort(403, __('auth.not_a_church_member'));
        }

        return redirect(ChurchHost::url($church, $request->getRequestUri()))
            ->with('info', __('workspace.use_church_portal'));
    }
}
