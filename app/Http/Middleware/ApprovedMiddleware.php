<?php
// app/Http/Middleware/ApprovedMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApprovedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_approved) {
            auth()->logout();
            return redirect()->route('login')->withErrors('Your account is not approved by admin yet.');
        }
        return $next($request);
    }
}

?>