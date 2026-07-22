<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\EventAdmin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Models\UserSystemRole;
use App\Tenancy\TenantContext;
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

    /** Staff permission keys (instructor + admin template intersection used for authz bundles). */
    public const STAFF_PERMISSION_KEYS = [
        'curriculum.manage',
        'attendance.record',
        'assignment.manage',
        'exam.author',
        'grade.manage',
        'role.manage',
    ];

    /** Learner permission keys (student template) used for authz bundles. */
    public const LEARNER_PERMISSION_KEYS = [
        'assignment.submit',
        'exam.take',
        'attendance.view_own',
    ];

    public function permissionsInCourse(User $user, Course $course): Collection
    {
        if (RolePreviewService::isActive() && ($user->is_superadmin ?? false)) {
            return $this->previewPermissionsInCourse($course);
        }

        if ($user->is_superadmin ?? false) {
            return $this->courseRbacReady() ? Permission::pluck('key') : collect();
        }

        $version = (int) ($course->permissions_version ?? 0);
        $serviceVersion = 0;
        if ($course->service_id && Schema::hasTable('service')) {
            $serviceVersion = (int) (DB::table('service')
                ->where('service_id', $course->service_id)
                ->value('permissions_version') ?? 0);
        }
        $churchVersion = 0;
        if ($course->church_id && Schema::hasTable('church')) {
            $churchVersion = (int) (DB::table('church')
                ->where('church_id', $course->church_id)
                ->value('permissions_version') ?? 0);
        }

        $cacheKey = "perms:{$course->course_id}:{$user->user_id}:{$version}:s{$serviceVersion}:c{$churchVersion}";

        return Cache::remember($cacheKey, 600, function () use ($user, $course) {
            return $this->resolveCoursePermissions($user, $course);
        });
    }

    public function permissionsInSystem(User $user): Collection
    {
        if (RolePreviewService::isActive() && ($user->is_superadmin ?? false)) {
            return $this->previewPermissionsInSystem();
        }

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
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if (! $this->permissionsInCourse($user, $course)->contains($permission)) {
            return false;
        }

        return $this->permissionAllowedByBoundChurch($permission);
    }

    public function canInSystem(User $user, string $permission): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if ($permission === 'events.admin' && EventAdmin::where('user_id', $user->user_id)->exists()) {
            return true;
        }

        return $this->permissionsInSystem($user)->contains($permission);
    }

    public function permissionsInService(User $user, ChurchService $service): Collection
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return $this->courseRbacReady() ? Permission::pluck('key') : collect();
        }

        if (! Schema::hasTable('user_service_role') || ! Schema::hasColumn('roles', 'service_id')) {
            return collect();
        }

        $version = (int) ($service->permissions_version ?? 0);
        $cacheKey = "perms:service:{$service->service_id}:{$user->user_id}:{$version}";

        return Cache::remember($cacheKey, 600, function () use ($user, $service) {
            $roleIds = UserServiceRole::where('user_id', $user->user_id)
                ->where('service_id', $service->service_id)
                ->pluck('role_id');

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

    public function canInService(User $user, string $permission, ChurchService $service): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if (! $this->permissionsInService($user, $service)->contains($permission)) {
            return false;
        }

        return $this->permissionAllowedByBoundChurch($permission);
    }

    /**
     * Effective permission keys for this user in a church (T3). Aggregates church-wide
     * grants plus course/service grants that belong to the church. Cached by
     * church.permissions_version.
     */
    public function permissionsInChurch(User $user, Church $church): Collection
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return $this->courseRbacReady() ? Permission::pluck('key') : collect();
        }

        if (! $this->courseRbacReady()) {
            return collect();
        }

        $version = (int) ($church->permissions_version ?? 1);
        $cacheKey = "perms:church:{$church->church_id}:{$user->user_id}:{$version}";

        return Cache::remember($cacheKey, 600, function () use ($user, $church) {
            return $this->resolveChurchPermissions($user, $church);
        });
    }

    public function canInChurch(User $user, string $permission, Church $church): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if (! $this->permissionAllowedByCapabilities($permission, $church)) {
            return false;
        }

        return $this->permissionsInChurch($user, $church)->contains($permission);
    }

    /**
     * Ceiling check: a permission listed under a capability may only be used when that
     * capability is enabled for the church. Permissions outside every capability list
     * are unrestricted by this ceiling (platform / unscoped keys).
     */
    public function permissionAllowedByCapabilities(string $permission, Church $church): bool
    {
        $capabilityKey = $this->capabilityKeyForPermission($permission);
        if ($capabilityKey === null) {
            return true;
        }

        return $church->hasCapability($capabilityKey);
    }

    public function bumpChurchPermissionsVersion(Church $church): void
    {
        $churchId = $church->church_id;
        $oldVersion = (int) ($church->permissions_version ?? 1);
        $church->bumpPermissionsVersion();
        $newVersion = $oldVersion + 1;

        $userIds = collect();
        if (Schema::hasTable('user_church_role')) {
            $userIds = $userIds->merge(
                UserChurchRole::where('church_id', $churchId)->pluck('user_id')
            );
        }
        if (Schema::hasColumn('user_course_role', 'church_id')) {
            $userIds = $userIds->merge(
                UserCourseRole::where('church_id', $churchId)->pluck('user_id')
            );
        }

        foreach ($userIds->unique() as $userId) {
            Cache::forget("perms:church:{$churchId}:{$userId}:{$oldVersion}");
            Cache::forget("perms:church:{$churchId}:{$userId}:{$newVersion}");
        }

        $church->refresh();
    }

    public function canAnyInCourse(User $user, array $permissions, Course $course): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        $held = $this->permissionsInCourse($user, $course);

        foreach ($permissions as $permission) {
            if ($held->contains($permission) && $this->permissionAllowedByBoundChurch($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAnyInSystem(User $user, array $permissions): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
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
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if (RolePreviewService::isActive() && ($user->is_superadmin ?? false)) {
            $previewCourseId = session(RolePreviewService::SESSION_COURSE_ID);
            $resolved = $course instanceof Course ? $course : Course::find($course);

            return $resolved
                && $previewCourseId
                && (int) $resolved->course_id === (int) $previewCourseId;
        }

        $course = $course instanceof Course ? $course : Course::find($course);
        if (! $course) {
            return false;
        }

        $assignment = UserCourseRole::where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->with('role')
            ->first();

        if (! $assignment) {
            // Hierarchical: service/church grants still count as access.
            return $this->permissionsInCourse($user, $course)->isNotEmpty();
        }

        if ($assignment->isStaffArchived()) {
            return $this->isLearnerRole($assignment->role);
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

        $serviceVersion = 0;
        if ($course->service_id && Schema::hasTable('service')) {
            $serviceVersion = (int) (DB::table('service')
                ->where('service_id', $course->service_id)
                ->value('permissions_version') ?? 0);
        }
        $churchVersion = 0;
        if ($course->church_id && Schema::hasTable('church')) {
            $churchVersion = (int) (DB::table('church')
                ->where('church_id', $course->church_id)
                ->value('permissions_version') ?? 0);
        }

        $userIds = UserCourseRole::where('course_id', $courseId)->pluck('user_id')->unique();
        foreach ($userIds as $userId) {
            foreach ([$oldVersion, $newVersion] as $v) {
                Cache::forget("perms:{$courseId}:{$userId}:{$v}");
                Cache::forget("perms:{$courseId}:{$userId}:{$v}:s{$serviceVersion}:c{$churchVersion}");
            }
        }

        $course->refresh();
    }

    public function clearUserCache(User $user, ?Course $course = null): void
    {
        if ($course) {
            $course->refresh();
            $version = (int) ($course->permissions_version ?? 0);
            $serviceVersion = 0;
            if ($course->service_id && Schema::hasTable('service')) {
                $serviceVersion = (int) (DB::table('service')
                    ->where('service_id', $course->service_id)
                    ->value('permissions_version') ?? 0);
            }
            $churchVersion = 0;
            if ($course->church_id && Schema::hasTable('church')) {
                $churchVersion = (int) (DB::table('church')
                    ->where('church_id', $course->church_id)
                    ->value('permissions_version') ?? 0);
            }

            foreach ([$version, max(0, $version - 1)] as $v) {
                Cache::forget("perms:{$course->course_id}:{$user->user_id}:{$v}");
                Cache::forget("perms:{$course->course_id}:{$user->user_id}:{$v}:s{$serviceVersion}:c{$churchVersion}");
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

    private function previewPermissionsInCourse(Course $course): Collection
    {
        if (! $this->courseRbacReady() || RolePreviewService::isGeneral()) {
            return collect();
        }

        $previewCourseId = session(RolePreviewService::SESSION_COURSE_ID);
        if (! $previewCourseId || (int) $course->course_id !== (int) $previewCourseId) {
            return collect();
        }

        $role = RolePreviewService::previewRole();
        if (! $role) {
            return collect();
        }

        return $this->permissionsForRole($role, $course);
    }

    private function previewPermissionsInSystem(): Collection
    {
        if (! RolePreviewService::isGeneral() || ! $this->systemRbacReady()) {
            return collect();
        }

        $role = RolePreviewService::previewRole();
        if (! $role) {
            return collect();
        }

        return $this->permissionsForRole($role, null);
    }

    private function permissionsForRole(Role $role, ?Course $course): Collection
    {
        if (! $this->courseRbacReady()) {
            return collect();
        }

        $keys = DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $role->role_id)
            ->whereNull('permissions.deprecated_at')
            ->pluck('permissions.key');

        if ($course instanceof Course) {
            return $this->filterByLifecycle($course, $keys);
        }

        return $keys;
    }

    /**
     * Hierarchical resolve: course assignment ∪ service role (course.service_id) ∪ church role.
     */
    private function resolveCoursePermissions(User $user, Course $course): Collection
    {
        if (! $this->courseRbacReady()) {
            return collect();
        }

        $roleIds = collect();

        $assignment = UserCourseRole::where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->with('role')
            ->first();

        if ($assignment?->role) {
            $archivedStaff = $assignment->isStaffArchived()
                && ! $this->isLearnerRole($assignment->role);

            if (! $archivedStaff) {
                $roleIds->push($assignment->role_id);
            }
        }

        if ($course->service_id
            && Schema::hasTable('user_service_role')
            && Schema::hasColumn('roles', 'service_id')) {
            $roleIds = $roleIds->merge(
                UserServiceRole::where('user_id', $user->user_id)
                    ->where('service_id', $course->service_id)
                    ->pluck('role_id')
            );
        }

        if ($course->church_id && Schema::hasTable('user_church_role')) {
            $roleIds = $roleIds->merge(
                UserChurchRole::where('user_id', $user->user_id)
                    ->where('church_id', $course->church_id)
                    ->pluck('role_id')
            );
        }

        $roleIds = $roleIds->unique()->filter()->values();
        if ($roleIds->isEmpty()) {
            return collect();
        }

        $keys = DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->whereIn('role_permission.role_id', $roleIds)
            ->whereNull('permissions.deprecated_at')
            ->distinct()
            ->pluck('permissions.key');

        return $this->filterByLifecycle($course, $keys);
    }

    /** True if the user holds any of the given keys in the course (hierarchical). */
    public function canAnyStaffInCourse(User $user, Course $course): bool
    {
        return $this->canAnyInCourse($user, self::STAFF_PERMISSION_KEYS, $course);
    }

    /** True if the user holds learner keys and not staff keys in the course. */
    public function canLearnerInCourse(User $user, Course $course): bool
    {
        if ($this->canAnyStaffInCourse($user, $course)) {
            return false;
        }

        return $this->canAnyInCourse($user, self::LEARNER_PERMISSION_KEYS, $course);
    }

    /**
     * Cross-course: holds any of $permissions in at least one enrolled / hierarchical context.
     *
     * @param  list<string>  $permissions
     */
    public function canAnyInAnyCourse(User $user, array $permissions): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        if (! $this->courseRbacReady()) {
            return false;
        }

        $courseIds = UserCourseRole::where('user_id', $user->user_id)->pluck('course_id');

        if (Schema::hasTable('user_service_role')) {
            $serviceIds = UserServiceRole::where('user_id', $user->user_id)->pluck('service_id');
            if ($serviceIds->isNotEmpty()) {
                $courseIds = $courseIds->merge(
                    Course::query()->withoutGlobalScope('church')
                        ->whereIn('service_id', $serviceIds)
                        ->pluck('course_id')
                );
            }
        }

        if (Schema::hasTable('user_church_role')) {
            $churchIds = UserChurchRole::where('user_id', $user->user_id)->pluck('church_id');
            if ($churchIds->isNotEmpty()) {
                $courseIds = $courseIds->merge(
                    Course::query()->withoutGlobalScope('church')
                        ->whereIn('church_id', $churchIds)
                        ->pluck('course_id')
                );
            }
        }

        $courseIds = $courseIds->unique()->filter()->values();
        if ($courseIds->isEmpty()) {
            return false;
        }

        foreach (Course::query()->withoutGlobalScope('church')->whereIn('course_id', $courseIds)->get() as $course) {
            if ($this->canAnyInCourse($user, $permissions, $course)) {
                return true;
            }
        }

        return false;
    }

    public function isStaffAnywhere(User $user): bool
    {
        return $this->canAnyInAnyCourse($user, self::STAFF_PERMISSION_KEYS);
    }

    public function isLearnerAnywhere(User $user): bool
    {
        if ($this->isStaffAnywhere($user)) {
            return false;
        }

        return $this->canAnyInAnyCourse($user, self::LEARNER_PERMISSION_KEYS);
    }

    public function isCourseAdminAnywhere(User $user): bool
    {
        return $this->canAnyInAnyCourse($user, ['role.manage'])
            || $this->canInSystem($user, 'system.role.manage');
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

    /** Learner roles keep access when staff_archived_at is set (students are not "staff"). */
    private function isLearnerRole(?Role $role): bool
    {
        if (! $role) {
            return false;
        }

        // Template slug identity (not authz by role name string in request paths).
        $slug = $role->effectiveSlug();

        return $slug === 'student';
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

    private function permissionAllowedByBoundChurch(string $permission): bool
    {
        $church = TenantContext::current();
        if (! $church) {
            return true; // tenancy dormant — no ceiling
        }

        return $this->permissionAllowedByCapabilities($permission, $church);
    }

    private function capabilityKeyForPermission(string $permission): ?string
    {
        foreach ((array) config('capabilities') as $capabilityKey => $def) {
            $keys = (array) ($def['permissions'] ?? []);
            if (in_array($permission, $keys, true)) {
                return $capabilityKey;
            }
        }

        return null;
    }

    private function resolveChurchPermissions(User $user, Church $church): Collection
    {
        $roleIds = collect();

        if (Schema::hasTable('user_church_role')) {
            $roleIds = $roleIds->merge(
                UserChurchRole::where('church_id', $church->church_id)
                    ->where('user_id', $user->user_id)
                    ->pluck('role_id')
            );
        }

        if (Schema::hasColumn('user_course_role', 'church_id')) {
            $roleIds = $roleIds->merge(
                UserCourseRole::where('user_id', $user->user_id)
                    ->where(function ($q) use ($church) {
                        $q->where('church_id', $church->church_id)
                            ->orWhereHas('course', fn ($c) => $c->withoutGlobalScope('church')
                                ->where('church_id', $church->church_id));
                    })
                    ->pluck('role_id')
            );
        } else {
            $roleIds = $roleIds->merge(
                UserCourseRole::where('user_id', $user->user_id)
                    ->whereHas('course', fn ($c) => $c->withoutGlobalScope('church')
                        ->where('church_id', $church->church_id))
                    ->pluck('role_id')
            );
        }

        if (Schema::hasTable('user_service_role') && Schema::hasColumn('service', 'church_id')) {
            $roleIds = $roleIds->merge(
                UserServiceRole::where('user_id', $user->user_id)
                    ->whereHas('service', fn ($s) => $s->withoutGlobalScope('church')
                        ->where('church_id', $church->church_id))
                    ->pluck('role_id')
            );
        }

        $roleIds = $roleIds->unique()->filter()->values();
        if ($roleIds->isEmpty()) {
            return collect();
        }

        return DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->whereIn('role_permission.role_id', $roleIds)
            ->whereNull('permissions.deprecated_at')
            ->distinct()
            ->pluck('permissions.key');
    }
}
