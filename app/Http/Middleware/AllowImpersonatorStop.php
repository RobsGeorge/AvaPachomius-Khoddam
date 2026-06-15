<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AllowImpersonatorStop
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! ImpersonationService::isActive() || ! ImpersonationService::impersonator()) {
            abort(403, __('pages.impersonate_not_active'));
        }

        return $next($request);
    }
}
