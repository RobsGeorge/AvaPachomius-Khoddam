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

        $allowed = ($user->is_superadmin ?? false)
            || $user->roles->contains('role_name', 'admin');

        if (! $allowed) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
