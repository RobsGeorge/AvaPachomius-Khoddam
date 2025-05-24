<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->roles->contains('role_name', 'Admin')) {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }
}

?>
