<?php

namespace App\Http\Middleware;

use App\Models\CourseApplicationForm;
use App\Models\User;
use App\Services\CourseApplicationService;
use App\Services\StudentRosterService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RequireApprovedCourseApplication
{
    public function __construct(
        private CourseApplicationService $applications,
        private StudentRosterService $roster,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('course_application_forms')) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $courseId = $request->route('course');
        if (! $courseId) {
            return $next($request);
        }

        $form = CourseApplicationForm::query()
            ->where('course_id', $courseId)
            ->where('is_enabled', true)
            ->first();

        if (! $form) {
            return $next($request);
        }

        if ($user->is_superadmin || $user->isAdmin()) {
            return $next($request);
        }

        if ($user->courses()->where('course.course_id', $courseId)->exists()
            && ! $user->isStudent()) {
            return $next($request);
        }

        if ($this->applications->isApprovedForCourse($user, (int) $courseId)) {
            return $next($request);
        }

        if ($this->routeIsAllowed($request)) {
            return $next($request);
        }

        $routeName = $this->applications->redirectRouteFor($user, (int) $courseId);

        return redirect()->route($routeName, $this->applications->redirectParamsFor((int) $courseId));
    }

    private function routeIsAllowed(Request $request): bool
    {
        $allowed = [
            'courses.application.status',
            'courses.application.edit',
            'courses.application.update',
            'courses.apply',
            'courses.apply.store',
            'course-applications.index',
            'available-courses.index',
            'profile',
            'profile.picture.update',
            'logout',
            'locale.switch',
            'theme.update',
            'notifications.index',
            'notifications.show',
            'notifications.settings',
            'notifications.settings.update',
            'notifications.mark-all-read',
            'notifications.reminders.store',
            'notifications.reminders.destroy',
            'application.status',
            'application.edit',
            'application.update',
        ];

        $name = $request->route()?->getName();

        return $name && in_array($name, $allowed, true);
    }
}
