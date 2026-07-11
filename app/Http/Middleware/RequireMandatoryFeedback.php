<?php

namespace App\Http\Middleware;

use App\Services\MandatoryFeedbackService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireMandatoryFeedback
{
    public function __construct(
        private MandatoryFeedbackService $mandatoryFeedback
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->isStudent()) {
            return $next($request);
        }

        if (! $this->mandatoryFeedback->hasPending($user)) {
            return $next($request);
        }

        if ($this->routeIsAllowed($request)) {
            return $next($request);
        }

        $pending = $this->mandatoryFeedback->firstPending($user);

        return redirect()
            ->route('feedback.surveys.show', $pending['survey_id'] ?? 0)
            ->with('warning', __('pages.mandatory_feedback_required'));
    }

    private function routeIsAllowed(Request $request): bool
    {
        $allowed = [
            'profile',
            'profile.picture.update',
            'logout',
            'feedback.index',
            'feedback.surveys.show',
            'feedback.surveys.submit',
            'announcements.index',
            'announcements.show',
            'announcements.dismiss-banner',
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
        ];

        $name = $request->route()?->getName();

        if ($name && in_array($name, $allowed, true)) {
            return true;
        }

        return false;
    }
}
