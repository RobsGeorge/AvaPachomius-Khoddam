<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;

/**
 * Course-scoped gate for gradebook mutations/reads that are reached via category
 * or item ids (no {course} in the URL). Coarse permission:staff alone is
 * staff-anywhere and must not authorize Course B from Course A staff.
 */
trait AuthorizesCourseGradeManage
{
    protected function authorizeCourseGradeManage(Course $course): void
    {
        $user = auth()->user();

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return;
        }

        $permissions = app(CoursePermissionResolver::class);

        abort_unless(
            $permissions->canInSystem($user, 'grade.manage')
            || $permissions->canInCourse($user, 'grade.manage', $course),
            403,
            __('pages.not_authorized')
        );
    }
}
