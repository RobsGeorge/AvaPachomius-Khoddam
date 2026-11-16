<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Session;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;

/**
 * Course-scoped gate for session attendance mutations/search.
 * Mirrors AttendanceController::authorizeAttendanceRecord: system OR course
 * attendance.record / attendance.edit (superadmin bypass via RolePreviewService).
 */
trait AuthorizesSessionAttendance
{
    protected function authorizeSessionAttendanceMutation(Session $session): void
    {
        $user = auth()->user();

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return;
        }

        $session->loadMissing('course');
        $course = $session->course;
        $keys = ['attendance.record', 'attendance.edit'];
        $permissions = app(CoursePermissionResolver::class);

        abort_unless(
            $course && (
                $permissions->canAnyInSystem($user, $keys)
                || $permissions->canAnyInCourse($user, $keys, $course)
            ),
            403,
            __('pages.not_authorized')
        );
    }
}
