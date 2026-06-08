<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
