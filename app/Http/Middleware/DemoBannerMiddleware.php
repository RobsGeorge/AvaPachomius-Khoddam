<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DemoBannerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.enabled') && $request->user()?->is_demo) {
            View::share('showDemoBanner', true);
        }

        return $next($request);
    }
}
