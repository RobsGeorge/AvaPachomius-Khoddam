<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->whereIn('role_name', $roles)->exists()) {
            abort(403, 'غير مصرح لك بالدخول.');
        }
        return $next($request);
    }
}

