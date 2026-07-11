<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized');
        }

        if ($user->is_superadmin ?? false) {
            return $next($request);
        }

        $systemPerms = [
            'system.role.manage', 'user.assign_role', 'registration.review',
            'course_application.review', 'course_application.form_builder',
            'translation.manage', 'attendance.configure', 'graduation.settings',
            'profile_photo.review', 'user.approve',
        ];

        foreach ($systemPerms as $perm) {
            if ($user->canInSystem($perm)) {
                return $next($request);
            }
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
