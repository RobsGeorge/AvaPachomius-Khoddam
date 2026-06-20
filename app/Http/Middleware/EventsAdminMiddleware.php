<?php

namespace App\Http\Middleware;

use App\Models\EventAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventsAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $allowed = ($user->is_superadmin ?? false)
            || $user->hasRole('admin')
            || EventAdmin::where('user_id', $user->user_id)->exists();

        if (! $allowed) {
            abort(403, __('events.admin_only'));
        }

        return $next($request);
    }
}
