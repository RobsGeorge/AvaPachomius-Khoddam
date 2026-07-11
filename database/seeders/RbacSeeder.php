<?php

namespace Database\Seeders;

use App\Models\EventAdmin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserCourseRole;
use App\Services\RoleTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync');

        Role::whereNull('slug')->each(function (Role $role) {
            $role->update([
                'slug' => Str::slug($role->role_name) ?: 'role-'.$role->role_id,
                'is_template' => $role->course_id === null && in_array(strtolower($role->role_name), ['admin', 'instructor', 'student'], true),
                'is_system' => $role->course_id === null,
            ]);
        });

        app(RoleTemplateService::class)->ensureSystemTemplates();

        $this->migrateExistingAssignmentsToCourseRoles();
        $this->migrateEventAdmins();
    }

    private function migrateExistingAssignmentsToCourseRoles(): void
    {
        $templateMap = Role::whereNull('course_id')
            ->where('is_template', true)
            ->get()
            ->keyBy(fn ($r) => strtolower($r->role_name));

        UserCourseRole::with('role')->chunkById(100, function ($assignments) use ($templateMap) {
            foreach ($assignments as $assignment) {
                $name = strtolower($assignment->role?->role_name ?? '');
                $template = $templateMap->get($name);

                if (! $template) {
                    continue;
                }

                $courseRole = Role::firstOrCreate(
                    [
                        'course_id' => $assignment->course_id,
                        'slug' => $template->effectiveSlug(),
                    ],
                    [
                        'role_name' => $template->role_name,
                        'role_decription' => $template->role_decription,
                        'description' => $template->description,
                        'cloned_from_role_id' => $template->role_id,
                    ]
                );

                if ($courseRole->permissions()->count() === 0) {
                    $courseRole->permissions()->sync($template->permissions()->pluck('permissions.permission_id'));
                }

                if ($assignment->role_id !== $courseRole->role_id) {
                    $assignment->update(['role_id' => $courseRole->role_id]);
                }
            }
        });
    }

    private function migrateEventAdmins(): void
    {
        $eventsPerm = Permission::where('key', 'events.admin')->first();
        if (! $eventsPerm) {
            return;
        }

        $role = Role::firstOrCreate(
            ['slug' => 'event-administrator', 'course_id' => null, 'is_template' => false],
            [
                'role_name' => 'Event Administrator',
                'role_decription' => 'Events admin',
                'description' => 'Migrated from event_admins table',
                'is_system' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$eventsPerm->permission_id]);

        foreach (EventAdmin::pluck('user_id') as $userId) {
            \App\Models\UserSystemRole::firstOrCreate([
                'user_id' => $userId,
                'role_id' => $role->role_id,
            ]);
        }
    }
}
