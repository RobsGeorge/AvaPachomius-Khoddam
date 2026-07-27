<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use App\Services\PlatformAccessService;
use App\Services\RolePreviewService;
use App\Support\ChurchHost;
use App\Support\SuperadminWorkspace;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Multi-tenant only: superadmin on a church host without View-as / platform-enter
 * is sent back to the Console registry (no silent god-mode browsing).
 */
class RedirectSuperadminWithoutTenantWorkflow
{
    /** @var list<string> */
    private const EXEMPT_PREFIXES = [
        '/superadmin',
        '/login',
        '/logout',
        '/locale/',
        '/theme/',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! config('tenancy.enabled') || ChurchHost::isConsoleHost()) {
            return $next($request);
        }

        $user = $request->user();
        if (! SuperadminWorkspace::isSuperadmin($user)) {
            return $next($request);
        }

        if (ImpersonationService::isActive()
            || RolePreviewService::isActive()
            || PlatformAccessService::isActive()) {
            return $next($request);
        }

        if (! TenantContext::current()) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        return redirect(ChurchHost::consoleUrl('/superadmin/churches'))
            ->with('info', __('workspace.superadmin_console_required'));
    }
}
