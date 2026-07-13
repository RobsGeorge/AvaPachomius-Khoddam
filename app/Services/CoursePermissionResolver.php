<?php

namespace App\Services;

use App\Models\Course;
use App\Models\EventAdmin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserSystemRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CoursePermissionResolver
{
    /** Write permission suffixes blocked by course lifecycle. */
    private const WRITE_SUFFIXES = ['.manage', '.grade', '.author', '.record', '.edit', '.close', '.publish', '.host', '.admin', '.configure'];

    private const LIFECYCLE_DENIED = [
        Course::STATUS_GRADING_LOCKED => ['grade.manage', 'exam.author', 'assignment.manage'],
        Course::STATUS_ANNOUNCED => ['grade.manage', 'exam.author', 'assignment.manage', 'curriculum.manage', 'announcement.manage', 'role.manage'],
        Course::STATUS_CLOSED => ['role.manage', 'user.assign_role'],
        Course::STATUS_ARCHIVED => ['role.manage', 'user.assign_role'],
    ];

    public function permissionsInCourse(User $user, Course $course): Collection
    {
        if ($user->is_superadmin ?? false) {
            return $this->courseRbacReady() ? Permission::pluck('key') : collect();
        }

        $version = (int) ($course->permissions_version ?? 0);
        $cacheKey = "perms:{$course->course_id}:{$user->user_id}:{$version}";

        return Cache::remember($cacheKey, 600, function () use ($user, $course) {
            return $this->resolveCoursePermissions($user, $course);
        });
    }

    public function permissionsInSystem(User $user): Collection
    {
        if ($user->is_superadmin ?? false) {
            return $this->systemRbacReady() ? Permission::pluck('key') : collect();
        }

        if (! $this->systemRbacReady()) {
            return collect();
        }

        $cacheKey = "perms:system:{$user->user_id}";

        return Cache::remember($cacheKey, 600, function () use ($user) {
            $roleIds = UserSystemRole::where('user_id', $user->user_id)->pluck('role_id');

            if ($roleIds->isEmpty()) {
                return collect();
            }

            return DB::table('role_permission')
                ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
                ->whereIn('role_permission.role_id', $roleIds)
                ->whereNull('permissions.deprecated_at')
                ->distinct()
                ->pluck('permissions.key');
        });
    }

    public function canInCourse(User $user, string $permission, Course $course): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        return $this->permissionsInCourse($user, $course)->contains($permission);
    }

    public function canInSystem(User $user, string $permission): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        if ($permission === 'events.admin' && EventAdmin::where('user_id', $user->user_id)->exists()) {
            return true;
        }

        return $this->permissionsInSystem($user)->contains($permission);
    }

    public function canAnyInCourse(User $user, array $permissions, Course $course): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $held = $this->permissionsInCourse($user, $course);

        foreach ($permissions as $permission) {
            if ($held->contains($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAnyInSystem(User $user, array $permissions): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $held = $this->permissionsInSystem($user);

        foreach ($permissions as $permission) {
            if ($held->contains($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasCourseAccess(User $user, Course|string $course): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $course = $course instanceof Course ? $course : Course::find($course);
        if (! $course) {
            return false;
        }

        $assignment = UserCourseRole::where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->first();

        if (! $assignment) {
            return false;
        }

        if ($assignment->staff_archived_at !== null) {
            return $this->isStudentAssignment($assignment);
        }

        return true;
    }

    public function bumpCoursePermissionsVersion(Course $course): void
    {
        $courseId = $course->course_id;
        $course->refresh();
        $oldVersion = (int) ($course->permissions_version ?? 0);

        $course->increment('permissions_version');
        $newVersion = $oldVersion + 1;

        $userIds = UserCourseRole::where('course_id', $courseId)->pluck('user_id')->unique();
        foreach ($userIds as $userId) {
            Cache::forget("perms:{$courseId}:{$userId}:{$oldVersion}");
            Cache::forget("perms:{$courseId}:{$userId}:{$newVersion}");
        }

        $course->refresh();
    }

    public function clearUserCache(User $user, ?Course $course = null): void
    {
        if ($course) {
            $course->refresh();
            $version = (int) ($course->permissions_version ?? 0);
            Cache::forget("perms:{$course->course_id}:{$user->user_id}:{$version}");
            if ($version > 0) {
                Cache::forget("perms:{$course->course_id}:{$user->user_id}:".($version - 1));
            }
        }
        Cache::forget("perms:system:{$user->user_id}");
    }

    public function isWritePermission(string $permission): bool
    {
        foreach (self::WRITE_SUFFIXES as $suffix) {
            if (Str::endsWith($permission, $suffix)) {
                return true;
            }
        }

        return in_array($permission, [
            'user.assign_role', 'course.close', 'assignment.submit',
            'exam.take', 'exam.proctor', 'events.reserve',
        ], true);
    }

    private function resolveCoursePermissions(User $user, Course $course): Collection
    {
        if (! $this->courseRbacReady()) {
            return collect();
        }

        $assignment = UserCourseRole::where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->with('role')
            ->first();

        if (! $assignment || ! $assignment->role) {
            return collect();
        }

        if ($assignment->staff_archived_at !== null && ! $this->isStudentAssignment($assignment)) {
            return $this->filterByLifecycle($course, collect());
        }

        $keys = DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $assignment->role_id)
            ->whereNull('permissions.deprecated_at')
            ->pluck('permissions.key');

        return $this->filterByLifecycle($course, $keys);
    }

    private function filterByLifecycle(Course $course, Collection $keys): Collection
    {
        $status = $course->status ?? Course::STATUS_ACTIVE;

        if ($status === Course::STATUS_ACTIVE || $status === null || $status === '') {
            return $keys;
        }

        $denied = collect(self::LIFECYCLE_DENIED[$status] ?? []);

        if (in_array($status, [Course::STATUS_CLOSED, Course::STATUS_ARCHIVED], true)) {
            return $keys->filter(function (string $key) {
                if ($this->isWritePermission($key)) {
                    return false;
                }

                return Str::endsWith($key, '.view')
                    || in_array($key, ['graduation.view', 'certificate.download', 'grade.view', 'course.access'], true);
            })->values();
        }

        if ($status === Course::STATUS_ANNOUNCED) {
            return $keys->reject(fn (string $key) => $denied->contains($key) || $this->isWritePermission($key))
                ->values();
        }

        return $keys->reject(fn (string $key) => $denied->contains($key))->values();
    }

    private function isStudentAssignment(UserCourseRole $assignment): bool
    {
        $slug = $assignment->role?->effectiveSlug() ?? '';
        $name = strtolower($assignment->role?->role_name ?? '');

        return $slug === 'student' || $name === 'student';
    }

    private function systemRbacReady(): bool
    {
        return Schema::hasTable('user_system_role')
            && Schema::hasTable('role_permission')
            && Schema::hasTable('permissions');
    }

    private function courseRbacReady(): bool
    {
        return Schema::hasTable('role_permission')
            && Schema::hasTable('permissions');
    }
}
