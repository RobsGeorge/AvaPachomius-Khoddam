<?php

namespace App\Http\Middleware;

use App\Services\ProfilePhotoGateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireProfilePhoto
{
    public function __construct(
        private ProfilePhotoGateService $photoGate
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $this->photoGate->ensureGraceStarted($user);

        if (! $this->photoGate->isHardBlocked($user)) {
            return $next($request);
        }

        if ($this->routeIsAllowed($request)) {
            return $next($request);
        }

        return redirect()
            ->route('profile')
            ->with('warning', __('pages.profile_photo_required_locked'));
    }

    private function routeIsAllowed(Request $request): bool
    {
        $allowed = [
            'profile',
            'profile.picture.update',
            'logout',
            'locale.switch',
            'theme.update',
            'onboarding.complete',
            'application.status',
            'application.edit',
            'application.update',
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
            'courses.select',
            'courses.select.store',
        ];

        $name = $request->route()?->getName();

        if (! $name) {
            return false;
        }

        if (in_array($name, $allowed, true)) {
            return true;
        }

        foreach (['admin.', 'hubs.', 'user-course-roles.', 'roles.', 'available-courses.', 'events.'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
