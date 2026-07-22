<?php

namespace App\Http\Middleware;

use App\Services\RolePreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /** System permission keys that unlock the legacy "admin" middleware gate. */
    private const SYSTEM_PERMS = [
        'system.role.manage', 'user.assign_role', 'registration.review',
        'course_application.review', 'course_application.form_builder',
        'service_application.review', 'service_application.form_builder',
        'translation.manage', 'attendance.configure', 'graduation.settings',
        'profile_photo.review', 'user.approve',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized');
        }

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return $next($request);
        }

        foreach (self::SYSTEM_PERMS as $perm) {
            if ($user->canInSystem($perm)) {
                return $next($request);
            }
        }

        // Course-scoped grants for application review / form builder (roles hub).
        if ($user->canAccessAdminCourseApplications() || $user->canAccessAdminCourseApplicationForms()) {
            return $next($request);
        }

        // Course admins (role.manage via hierarchical resolver) keep access.
        if ($user->isAdmin()) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
