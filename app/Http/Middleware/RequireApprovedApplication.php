<?php

namespace App\Http\Middleware;

use App\Services\RegistrationApplicationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RequireApprovedApplication
{
    public function __construct(
        private RegistrationApplicationService $applications
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasColumn('user', 'application_status')) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user || $user->is_superadmin || $user->isAdmin()) {
            return $next($request);
        }

        if ($this->applications->isApproved($user)) {
            return $next($request);
        }

        if ($this->routeIsAllowed($request)) {
            return $next($request);
        }

        return redirect()->route($this->applications->redirectRouteFor($user));
    }

    private function routeIsAllowed(Request $request): bool
    {
        $allowed = [
            'application.status',
            'application.edit',
            'application.update',
            'profile',
            'profile.picture.update',
            'logout',
            'locale.switch',
            'theme.update',
            'home',
            'login',
            'notifications.index',
            'notifications.show',
            'notifications.settings',
            'notifications.settings.update',
            'notifications.mark-all-read',
            'notifications.reminders.store',
            'notifications.reminders.destroy',
            'course-applications.index',
            'available-courses.index',
            'courses.apply',
            'courses.apply.store',
            'courses.application.status',
            'courses.application.edit',
            'courses.application.update',
        ];

        $name = $request->route()?->getName();

        return $name && in_array($name, $allowed, true);
    }
}
