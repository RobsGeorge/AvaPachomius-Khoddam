<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Church;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return $next($request);
        }

        if (in_array('staff', $permissions, true)) {
            if ($this->hasStaffAccess($user)) {
                return $next($request);
            }
            abort(403, __('pages.not_authorized'));
        }

        $course = $this->resolveCourse($request);
        $isSystem = $this->isSystemPermission($permissions);

        foreach ($permissions as $permission) {
            if ($permission === 'staff') {
                continue;
            }

            if ($this->isChurchPermission($permission) && $this->canInResolvedChurch($user, $permission)) {
                return $next($request);
            }

            if ($isSystem || str_starts_with($permission, 'platform.') || str_starts_with($permission, 'system.')) {
                if ($this->resolver->canInSystem($user, $permission)) {
                    return $next($request);
                }
            } elseif ($course instanceof Course) {
                if ($this->resolver->canInCourse($user, $permission, $course)) {
                    return $next($request);
                }
            } else {
                if ($this->resolver->canInSystem($user, $permission)) {
                    return $next($request);
                }

                if ($this->hasPermissionInAnyCourse($user, $permission)) {
                    return $next($request);
                }
            }
        }

        abort(403, __('pages.not_authorized'));
    }

    private function isChurchPermission(string $permission): bool
    {
        foreach (['church.', 'priest.', 'confession.', 'home_visit.', 'finance.'] as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function canInResolvedChurch($user, string $permission): bool
    {
        $church = TenantContext::current();
        if (! $church && Schema::hasTable('church')) {
            $church = Church::query()->where('slug', config('tenancy.main_slug'))->first();
        }

        return $church
            && $this->resolver->canInChurch($user, $permission, $church);
    }

    private function hasStaffAccess($user): bool
    {
        $staffKeys = [
            'curriculum.manage', 'exam.author', 'assignment.manage', 'grade.manage',
            'attendance.record', 'attendance.view_all', 'announcement.manage',
            'roster.view', 'graduation.view', 'course.close', 'feedback.manage',
            'live_quiz.manage', 'role.manage',
        ];

        foreach ($staffKeys as $key) {
            if ($this->resolver->canInSystem($user, $key)) {
                return true;
            }
            if ($this->hasPermissionInAnyCourse($user, $key)) {
                return true;
            }
        }

        return $this->resolver->isStaffAnywhere($user);
    }

    private function resolveCourse(Request $request): ?Course
    {
        $course = $request->route('course');

        if ($course instanceof Course) {
            return $course;
        }

        if (is_string($course) && $course !== '') {
            return Course::find($course);
        }

        foreach (['courseId', 'course_id'] as $param) {
            $value = $request->route($param);
            if ($value) {
                return Course::find($value);
            }
        }

        return current_course();
    }

    private function isSystemPermission(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (str_starts_with($p, 'platform.') || str_starts_with($p, 'system.')
                || in_array($p, ['user.approve', 'registration.review', 'registration.send_link',
                    'course_application.review', 'course_application.form_builder',
                    'translation.manage', 'attendance.configure', 'graduation.settings', 'profile_photo.review'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermissionInAnyCourse($user, string $permission): bool
    {
        $courseIds = $user->userCourseRoles()
            ->activeStaff()
            ->pluck('course_id')
            ->unique();

        foreach ($courseIds as $courseId) {
            $course = Course::find($courseId);
            if ($course && $this->resolver->canInCourse($user, $permission, $course)) {
                return true;
            }
        }

        return false;
    }
}
