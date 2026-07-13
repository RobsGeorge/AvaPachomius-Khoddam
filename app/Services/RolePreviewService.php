<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RolePreviewService
{
    public const SESSION_ROLE_ID = 'role_preview_role_id';

    public const SESSION_COURSE_ID = 'role_preview_course_id';

    public const SESSION_IS_GENERAL = 'role_preview_is_general';

    public const SESSION_PREVIOUS_COURSE_ID = 'role_preview_previous_course_id';

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_ROLE_ID);
    }

    public static function superadminBypassesPermissions(User $user): bool
    {
        return ($user->is_superadmin ?? false) && ! self::isActive();
    }

    public static function previewRole(): ?Role
    {
        if (! self::isActive()) {
            return null;
        }

        return Role::query()->find(session(self::SESSION_ROLE_ID));
    }

    public static function previewCourse(): ?Course
    {
        if (! self::isActive() || session(self::SESSION_IS_GENERAL)) {
            return null;
        }

        $courseId = session(self::SESSION_COURSE_ID);

        return $courseId ? Course::query()->find($courseId) : null;
    }

    public static function isGeneral(): bool
    {
        return self::isActive() && (bool) session(self::SESSION_IS_GENERAL, false);
    }

    /** @return array{role: Role, course: ?Course, is_general: bool, course_id: ?int}|null */
    public static function context(): ?array
    {
        $role = self::previewRole();
        if (! $role) {
            return null;
        }

        $isGeneral = self::isGeneral();

        return [
            'role' => $role,
            'course' => $isGeneral ? null : self::previewCourse(),
            'is_general' => $isGeneral,
            'course_id' => $isGeneral ? null : session(self::SESSION_COURSE_ID),
        ];
    }

    public static function matchesRoleName(User $user, string $roleName, ?string $courseId = null): bool
    {
        if (! ($user->is_superadmin ?? false) || ! self::isActive()) {
            return false;
        }

        $role = self::previewRole();
        if (! $role) {
            return false;
        }

        $matchesName = strcasecmp($role->role_name, $roleName) === 0
            || $role->effectiveSlug() === strtolower($roleName);

        if (! $matchesName) {
            return false;
        }

        if (self::isGeneral()) {
            return $courseId === null;
        }

        if ($courseId !== null) {
            return (int) $courseId === (int) session(self::SESSION_COURSE_ID);
        }

        return true;
    }

    public static function startCourseRole(User $superadmin, Course $course, Role $role, Request $request): void
    {
        self::guardSuperadmin($superadmin);
        self::guardNotImpersonating();

        if ((int) $role->course_id !== (int) $course->course_id || $role->is_template) {
            throw ValidationException::withMessages([
                'role_id' => [__('pages.role_preview_invalid_role')],
            ]);
        }

        $previousCourseId = session(CourseContextService::SESSION_KEY);
        session([self::SESSION_PREVIOUS_COURSE_ID => $previousCourseId]);

        session([
            self::SESSION_ROLE_ID => $role->role_id,
            self::SESSION_COURSE_ID => $course->course_id,
            self::SESSION_IS_GENERAL => false,
        ]);

        app(CourseContextService::class)->setCurrentCourse($superadmin, (int) $course->course_id);

        self::logEvent($request, 'role_preview_started', $superadmin, $role, $course);
    }

    public static function startGeneralRole(User $superadmin, Role $role, Request $request): void
    {
        self::guardSuperadmin($superadmin);
        self::guardNotImpersonating();

        if ($role->course_id !== null || $role->is_template || ! $role->is_system) {
            throw ValidationException::withMessages([
                'role_id' => [__('pages.role_preview_invalid_role')],
            ]);
        }

        $previousCourseId = session(CourseContextService::SESSION_KEY);
        session([self::SESSION_PREVIOUS_COURSE_ID => $previousCourseId]);

        session([
            self::SESSION_ROLE_ID => $role->role_id,
            self::SESSION_COURSE_ID => null,
            self::SESSION_IS_GENERAL => true,
        ]);

        app(CourseContextService::class)->clearCurrentCourse();

        self::logEvent($request, 'role_preview_started', $superadmin, $role, null);
    }

    public static function stop(Request $request): void
    {
        if (! self::isActive()) {
            abort(403, __('pages.role_preview_not_active'));
        }

        $user = $request->user();
        if (! $user || ! ($user->is_superadmin ?? false)) {
            session()->forget([
                self::SESSION_ROLE_ID,
                self::SESSION_COURSE_ID,
                self::SESSION_IS_GENERAL,
                self::SESSION_PREVIOUS_COURSE_ID,
            ]);
            abort(403, __('pages.role_preview_not_active'));
        }

        $role = self::previewRole();
        $course = self::previewCourse();
        $previousCourseId = session(self::SESSION_PREVIOUS_COURSE_ID);

        session()->forget([
            self::SESSION_ROLE_ID,
            self::SESSION_COURSE_ID,
            self::SESSION_IS_GENERAL,
            self::SESSION_PREVIOUS_COURSE_ID,
        ]);

        $courseContext = app(CourseContextService::class);
        if ($previousCourseId) {
            $courseContext->setCurrentCourse($user, (int) $previousCourseId);
        } else {
            $courseContext->clearCurrentCourse();
        }

        if ($role) {
            self::logEvent($request, 'role_preview_stopped', $user, $role, $course);
        }
    }

    public static function label(): string
    {
        $role = self::previewRole();
        if (! $role) {
            return '';
        }

        if (self::isGeneral()) {
            return __('pages.role_preview_general_label', ['role' => $role->role_name]);
        }

        $course = self::previewCourse();

        return __('pages.role_preview_course_label', [
            'role' => $role->role_name,
            'course' => $course?->localizedTitle() ?? __('pages.not_available'),
        ]);
    }

    private static function guardSuperadmin(User $user): void
    {
        if (! ($user->is_superadmin ?? false)) {
            abort(403);
        }

        if (self::isActive()) {
            throw ValidationException::withMessages([
                'role_id' => [__('pages.role_preview_already_active')],
            ]);
        }
    }

    private static function guardNotImpersonating(): void
    {
        if (ImpersonationService::isActive()) {
            throw ValidationException::withMessages([
                'role_id' => [__('pages.role_preview_while_impersonating')],
            ]);
        }
    }

    private static function logEvent(
        Request $request,
        string $event,
        User $superadmin,
        Role $role,
        ?Course $course,
    ): void {
        try {
            AuditLogService::logImpersonationEvent(
                $request,
                $event,
                (int) $superadmin->user_id,
                (int) $role->role_id,
            );
        } catch (\Throwable) {
            // Audit must not block preview flow.
        }
    }
}
