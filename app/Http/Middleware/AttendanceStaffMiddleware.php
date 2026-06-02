<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceStaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'))->withErrors(__('auth.login_required'));
        }

        if ($user->is_superadmin || $user->hasAnyRole(['admin', 'instructor'])) {
            return $next($request);
        }

        abort(403, __('pages.not_authorized'));
    }
}
