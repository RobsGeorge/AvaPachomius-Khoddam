<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\Role;
use App\Models\User;
use App\Models\UserServiceRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceRoleAssignmentService
{
    public static function schemaReady(): bool
    {
        return Schema::hasTable('user_service_role')
            && Schema::hasTable('service')
            && Schema::hasColumn('roles', 'service_id');
    }

    public function memberRoleFor(ChurchService $service): Role
    {
        $role = Role::query()
            ->where('service_id', $service->service_id)
            ->whereNull('course_id')
            ->where('is_template', false)
            ->where('slug', 'service-member')
            ->first();

        if ($role) {
            return $role;
        }

        return Role::create([
            'role_name' => 'Service Member',
            'role_decription' => 'Default service membership',
            'slug' => 'service-member',
            'course_id' => null,
            'service_id' => $service->service_id,
            'is_system' => false,
            'is_template' => false,
        ]);
    }

    public function adminRoleFor(ChurchService $service): Role
    {
        $role = Role::query()
            ->where('service_id', $service->service_id)
            ->whereNull('course_id')
            ->where('is_template', false)
            ->where('slug', 'service-admin')
            ->first();

        if ($role) {
            return $role;
        }

        $role = Role::create([
            'role_name' => 'Service Admin',
            'role_decription' => 'Service administrator',
            'slug' => 'service-admin',
            'course_id' => null,
            'service_id' => $service->service_id,
            'is_system' => false,
            'is_template' => false,
        ]);

        $permissionIds = \App\Models\Permission::query()
            ->whereIn('key', [
                'service.view',
                'service.manage',
                'service.member.add',
                'service.member.remove',
                'service.member.add_cross',
                'service.role.manage',
                'service.user.assign_role',
            ])
            ->pluck('permission_id');

        if ($permissionIds->isNotEmpty()) {
            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }

    public function assign(
        User $user,
        ChurchService $service,
        Role $role,
        bool $asPrimary = false,
        bool $allowCrossService = false,
    ): UserServiceRole {
        if (! self::schemaReady()) {
            throw ValidationException::withMessages([
                'service' => __('service.schema_missing'),
            ]);
        }

        $this->assertAssignableServiceRole($service, $role);

        $existingInService = UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->first();

        if ($existingInService) {
            if ((int) $existingInService->role_id === (int) $role->role_id) {
                return $existingInService;
            }

            $existingInService->role_id = $role->role_id;
            $existingInService->save();
            $service->bumpPermissionsVersion();

            return $existingInService->fresh();
        }

        $membershipCount = UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->count();

        if ($membershipCount > 0 && ! $allowCrossService) {
            throw ValidationException::withMessages([
                'service' => __('service.cross_add_required'),
            ]);
        }

        if ($membershipCount > 0 && $allowCrossService && ! $asPrimary) {
            // Cross-add into another service — keep existing primary.
        }

        return DB::transaction(function () use ($user, $service, $role, $asPrimary, $membershipCount) {
            $makePrimary = $asPrimary || $membershipCount === 0;

            if ($makePrimary) {
                UserServiceRole::query()
                    ->where('user_id', $user->user_id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $assignment = UserServiceRole::create([
                'user_id' => $user->user_id,
                'service_id' => $service->service_id,
                'role_id' => $role->role_id,
                'is_primary' => $makePrimary,
            ]);

            $service->bumpPermissionsVersion();

            return $assignment;
        });
    }

    public function addCrossService(User $user, ChurchService $service, ?Role $role = null): UserServiceRole
    {
        if (! UserServiceRole::query()->where('user_id', $user->user_id)->exists()) {
            throw ValidationException::withMessages([
                'user' => __('service.cross_add_needs_existing'),
            ]);
        }

        $role ??= $this->memberRoleFor($service);

        return $this->assign($user, $service, $role, asPrimary: false, allowCrossService: true);
    }

    public function remove(User $user, ChurchService $service): void
    {
        $row = UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->first();

        if (! $row) {
            return;
        }

        $wasPrimary = (bool) $row->is_primary;
        $row->delete();

        if ($wasPrimary) {
            $next = UserServiceRole::query()
                ->where('user_id', $user->user_id)
                ->orderBy('user_service_role_id')
                ->first();
            if ($next) {
                $next->is_primary = true;
                $next->save();
            }
        }

        $service->bumpPermissionsVersion();
    }

    public function userBelongsToService(User $user, ChurchService|int $service): bool
    {
        if (! self::schemaReady()) {
            return true;
        }

        $serviceId = $service instanceof ChurchService ? $service->service_id : $service;

        return UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $serviceId)
            ->exists();
    }

    private function assertAssignableServiceRole(ChurchService $service, Role $role): void
    {
        if ($role->is_template) {
            throw ValidationException::withMessages([
                'role_id' => __('service.invalid_role'),
            ]);
        }

        if ((int) $role->service_id !== (int) $service->service_id || $role->course_id !== null) {
            throw ValidationException::withMessages([
                'role_id' => __('service.invalid_role'),
            ]);
        }
    }
}
