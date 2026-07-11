<?php

namespace App\Http\Middleware;

use App\Models\EventAdmin;
use App\Services\CoursePermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceStaffMiddleware
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'))->withErrors(__('auth.login_required'));
        }

        if ($user->is_superadmin ?? false) {
            return $next($request);
        }

        foreach (['attendance.record', 'attendance.view_all'] as $perm) {
            if ($user->canInSystem($perm) || $this->hasInAnyCourse($user, $perm)) {
                return $next($request);
            }
        }

        if ($user->hasAnyRole(['admin', 'instructor'])) {
            return $next($request);
        }

        abort(403, __('pages.not_authorized'));
    }

    private function hasInAnyCourse($user, string $perm): bool
    {
        foreach ($user->userCourseRoles()->whereNull('staff_archived_at')->pluck('course_id') as $courseId) {
            $course = \App\Models\Course::find($courseId);
            if ($course && $this->resolver->canInCourse($user, $perm, $course)) {
                return true;
            }
        }

        return false;
    }
}
