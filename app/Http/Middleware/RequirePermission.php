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
            // When the route carries an explicit course id, staff access must be held
            // *in that course* (or system-wide) — not merely as staff of some other course.
            $explicitCourse = $this->explicitRouteCourse($request);
            if ($explicitCourse instanceof Course) {
                if ($this->hasStaffAccessInCourse($user, $explicitCourse)) {
                    return $next($request);
                }
                abort(403, __('pages.not_authorized'));
            }

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
        foreach (['church.', 'priest.', 'confession.', 'appointment.', 'home_visit.', 'finance.', 'public_site.', 'people.'] as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function canInResolvedChurch($user, string $permission): bool
    {
        $church = TenantContext::current();
        // When MULTI_TENANT is on, never fall back to Tenant Zero — console host is
        // unbound on purpose (superadmin only). Falling back caused demo priests/
        // members on admin.* to be checked against the wrong church → 403.
        if (! $church && ! config('tenancy.enabled') && Schema::hasTable('church')) {
            $church = Church::query()->where('slug', config('tenancy.main_slug'))->first();
        }

        return $church
            && $this->resolver->canInChurch($user, $permission, $church);
    }

    /** Permission keys that qualify a user as "staff" for the coarse `permission:staff` gate. */
    private const STAFF_KEYS = [
        'curriculum.manage', 'exam.author', 'assignment.manage', 'grade.manage',
        'attendance.record', 'attendance.view_all', 'announcement.manage',
        'roster.view', 'graduation.view', 'course.close', 'feedback.manage',
        'live_quiz.manage', 'role.manage',
    ];

    private function hasStaffAccess($user): bool
    {
        foreach (self::STAFF_KEYS as $key) {
            if ($this->resolver->canInSystem($user, $key)) {
                return true;
            }
            if ($this->hasPermissionInAnyCourse($user, $key)) {
                return true;
            }
        }

        return $this->resolver->isStaffAnywhere($user);
    }

    /** True when the user holds any staff permission system-wide or *within this course*. */
    private function hasStaffAccessInCourse($user, Course $course): bool
    {
        foreach (self::STAFF_KEYS as $key) {
            if ($this->resolver->canInSystem($user, $key)) {
                return true;
            }
            if ($this->resolver->canInCourse($user, $key, $course)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a course from an *explicit* route parameter only ({course}/{courseId}/
     * {course_id}). Unlike {@see resolveCourse()} this never falls back to the ambient
     * current_course(), so course-less staff dashboards keep staff-anywhere semantics.
     */
    private function explicitRouteCourse(Request $request): ?Course
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

        // Exam-scoped admin routes ({exam}) derive their course from the exam.
        $exam = $request->route('exam');
        if ($exam instanceof \App\Models\Exam) {
            return $exam->course;
        }
        if (is_string($exam) && $exam !== '') {
            return \App\Models\Exam::find($exam)?->course;
        }

        return null;
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
