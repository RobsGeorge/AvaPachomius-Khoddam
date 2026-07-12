<?php

namespace App\Http\Middleware;

use App\Services\CourseContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseContext
{
    /** @var list<string> */
    private array $exceptRouteNames = [
        'courses.select',
        'courses.select.store',
        'logout',
        'home',
        'locale.switch',
        'theme.update',
        'profile',
        'notifications.index',
        'notifications.show',
        'notifications.settings',
        'notifications.settings.update',
        'notifications.reminders.store',
        'notifications.reminders.destroy',
        'notifications.mark-all-read',
        'application.status',
        'application.edit',
        'application.update',
        'courses.apply',
        'courses.apply.store',
        'courses.application.status',
        'courses.application.edit',
        'courses.application.update',
        'onboarding.complete',
    ];

    public function __construct(
        private CourseContextService $courseContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->courseContext->requiresCourseContext($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && $this->isExceptedRoute($routeName)) {
            return $next($request);
        }

        $this->courseContext->autoSelectSingleCourse($user);

        if ($this->courseContext->currentCourse($user)) {
            return $next($request);
        }

        if ($this->courseContext->selectableCourses($user)->isEmpty()) {
            return $next($request);
        }

        return redirect()->route('courses.select', [
            'intended' => $request->fullUrl(),
        ]);
    }

  /** @var list<string> */
    private array $exceptRoutePrefixes = [
        'admin.',
        'superadmin.',
        'user-course-roles.',
        'roles.',
        'hubs.',
        'available-courses.',
        'events.',
    ];

    private function isExceptedRoute(string $routeName): bool
    {
        if (in_array($routeName, $this->exceptRouteNames, true)) {
            return true;
        }

        foreach ($this->exceptRoutePrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
