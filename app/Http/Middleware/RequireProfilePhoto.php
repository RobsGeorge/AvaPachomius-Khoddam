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
            'profile.preferences.update',
            'logout',
            'locale.switch',
            'theme.update',
            'onboarding.complete',
        ];

        $name = $request->route()?->getName();

        return $name && in_array($name, $allowed, true);
    }
}
