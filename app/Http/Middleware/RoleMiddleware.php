<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $userRoles = $request->user()->roles->pluck('role_name')->toArray();

        $hasRole = false;
        foreach ($roles as $role) {
            foreach ($userRoles as $userRole) {
                if (strcasecmp($role, $userRole) === 0) {
                    $hasRole = true;
                    break 2;
                }
            }
        }

        if (!$hasRole) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}

