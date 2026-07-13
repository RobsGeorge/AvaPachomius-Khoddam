<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\ChurchService;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\CoursePermissionResolver;
use Illuminate\Support\Collection;

class RolePermissionPolicy
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function manageCourseRoles(User $user, Course $course): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInCourse($user, 'role.manage', $course);
    }

    public function assignUsers(User $user, Course $course): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInCourse($user, 'user.assign_role', $course);
    }

    public function manageServiceRoles(User $user, ChurchService $service): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInService($user, 'service.role.manage', $service)
            || $this->resolver->canInService($user, 'service.manage', $service);
    }

    public function assignServiceUsers(User $user, ChurchService $service): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInService($user, 'service.user.assign_role', $service)
            || $this->resolver->canInService($user, 'service.member.add', $service);
    }

    public function addCrossServiceMember(User $user, ChurchService $service): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInService($user, 'service.member.add_cross', $service);
    }

    public function removeServiceMember(User $user, ChurchService $service): bool
    {
        return ($user->is_superadmin ?? false)
            || $this->resolver->canInService($user, 'service.member.remove', $service);
    }

    public function updateRolePermissions(User $user, Role $role, array $permissionIds): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        if ($role->is_template || ! $role->course_id) {
            return false;
        }

        $course = $role->course;
        if (! $course || ! $this->resolver->canInCourse($user, 'role.manage', $course)) {
            return false;
        }

        $requested = Permission::whereIn('permission_id', $permissionIds)->get();
        $held = $this->resolver->permissionsInCourse($user, $course);

        foreach ($requested as $perm) {
            if ($perm->is_system_only) {
                return false;
            }
            if (! $held->contains($perm->key)) {
                return false;
            }
            if (! $this->isGroupVisibleToCourseAdmins($perm->permission_group_id)) {
                return false;
            }
        }

        if (! $held->contains('role.manage') && $this->removesLastRoleManager($role, $requested)) {
            return false;
        }

        return true;
    }

    public function deleteRole(User $user, Role $role): bool
    {
        if ($role->userCourseRoles()->exists()) {
            return false;
        }

        if ($user->is_superadmin ?? false) {
            return true;
        }

        if (! $role->course_id) {
            return false;
        }

        return $this->resolver->canInCourse($user, 'role.manage', $role->course);
    }

    public function visibleGroupsForCourseAdmin(): Collection
    {
        return PermissionGroup::query()
            ->whereIn('scope', ['course', 'both'])
            ->where(function ($q) {
                $q->whereHas('visibility', fn ($v) => $v->where('visible_to_course_admins', true))
                    ->orWhereDoesntHave('visibility');
            })
            ->orderBy('sort_order')
            ->with('permissions')
            ->get();
    }

    private function isGroupVisibleToCourseAdmins(int $groupId): bool
    {
        $visibility = \App\Models\CourseAdminGroupVisibility::where('permission_group_id', $groupId)->first();

        return $visibility ? $visibility->visible_to_course_admins : true;
    }

    private function removesLastRoleManager(Role $role, Collection $requested): bool
    {
        $roleManagePerm = Permission::where('key', 'role.manage')->first();
        if (! $roleManagePerm) {
            return false;
        }

        $currentlyHas = $role->permissions()->where('permissions.permission_id', $roleManagePerm->permission_id)->exists();
        $willHave = $requested->contains('permission_id', $roleManagePerm->permission_id);

        if ($currentlyHas && ! $willHave) {
            $otherManagers = UserCourseRole::where('course_id', $role->course_id)
                ->where('role_id', '!=', $role->role_id)
                ->whereHas('role.permissions', fn ($q) => $q->where('permissions.key', 'role.manage'))
                ->count();

            return $otherManagers === 0;
        }

        return false;
    }
}
