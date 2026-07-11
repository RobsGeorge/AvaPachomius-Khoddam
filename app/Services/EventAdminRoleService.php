<?php

namespace App\Services;

use App\Models\EventAdmin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSystemRole;

class EventAdminRoleService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function ensureRole(): Role
    {
        $eventsPerm = Permission::where('key', 'events.admin')->first();

        $role = Role::firstOrCreate(
            ['slug' => 'event-administrator', 'course_id' => null, 'is_template' => false],
            [
                'role_name' => 'Event Administrator',
                'role_decription' => 'Events admin',
                'description' => 'Can create and manage institution events',
                'is_system' => true,
            ]
        );

        if ($eventsPerm) {
            $role->permissions()->syncWithoutDetaching([$eventsPerm->permission_id]);
        }

        return $role;
    }

    public function grant(User $user): void
    {
        $role = $this->ensureRole();

        UserSystemRole::firstOrCreate([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);

        $this->resolver->clearUserCache($user);
    }

    public function revoke(User $user): void
    {
        $role = Role::where('slug', 'event-administrator')
            ->whereNull('course_id')
            ->first();

        if ($role) {
            UserSystemRole::where('user_id', $user->user_id)
                ->where('role_id', $role->role_id)
                ->delete();
        }

        $this->resolver->clearUserCache($user);
    }

    public function syncFromEventAdminsTable(): void
    {
        $role = $this->ensureRole();

        foreach (EventAdmin::pluck('user_id') as $userId) {
            UserSystemRole::firstOrCreate([
                'user_id' => $userId,
                'role_id' => $role->role_id,
            ]);
        }
    }
}
