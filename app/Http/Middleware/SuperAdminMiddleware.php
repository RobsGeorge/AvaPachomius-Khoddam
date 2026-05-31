<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->is_superadmin) {
            abort(403, 'هذه الصفحة مخصصة للمشرف العام فقط.');
        }

        return $next($request);
    }
}
