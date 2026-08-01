<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use App\Services\BreakGlass\BreakGlassService;
use App\Support\ChurchHost;
use Illuminate\Http\Request;

/**
 * Breaks-glass superadmin access to a church host with platform bypass still on.
 * Requires an unexpired {@see BreakGlassGrant} for the church's placement organization.
 * Mutually exclusive with role preview / impersonation.
 */
class PlatformAccessService
{
    public const SESSION_KEY = 'platform_church_access_id';

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function churchId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }

    public static function church(): ?Church
    {
        $id = self::churchId();

        return $id ? Church::query()->find($id) : null;
    }

    public static function start(Church $church, User $superadmin, Request $request): void
    {
        if (! ($superadmin->is_superadmin ?? false)) {
            abort(403);
        }

        if (ImpersonationService::isActive()) {
            abort(403, __('workspace.platform_access_while_impersonating'));
        }

        if (RolePreviewService::isActive()) {
            RolePreviewService::stop($request);
        }

        // Wired break-glass: no standing prod access without an unexpired grant.
        app(BreakGlassService::class)->assertAllowed($superadmin, $church);

        $request->session()->put(self::SESSION_KEY, (int) $church->church_id);

        try {
            AuditLogService::recordEvent('platform_church_access_started', [
                'church_id' => (int) $church->church_id,
                'church_slug' => $church->slug,
            ]);
        } catch (\Throwable) {
            // Audit must not block access flow.
        }
    }

    public static function stop(Request $request): void
    {
        $churchId = self::churchId();
        $request->session()->forget(self::SESSION_KEY);

        if ($churchId) {
            try {
                AuditLogService::recordEvent('platform_church_access_stopped', [
                    'church_id' => $churchId,
                ]);
            } catch (\Throwable) {
                // Audit must not block exit flow.
            }
        }
    }

    public static function consoleExitUrl(): string
    {
        return ChurchHost::consoleUrl('/superadmin/churches');
    }
}
