<?php

namespace App\Console\Commands;

use App\Models\CourseAdminGroupVisibility;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {--check : Exit with error if unmapped routes exist}';

    protected $description = 'Sync permission registry from config/permissions.php to database';

    public function handle(): int
    {
        $catalog = config('permissions', []);
        $configKeys = [];

        foreach ($catalog as $groupKey => $groupDef) {
            $newScope = $groupDef['scope'] ?? 'course';
            $existingGroup = PermissionGroup::query()->where('group_key', $groupKey)->first();
            $previousScope = $existingGroup?->scope;

            $group = PermissionGroup::updateOrCreate(
                ['group_key' => $groupKey],
                [
                    'label_en' => $groupDef['label_en'] ?? $groupKey,
                    'label_ar' => $groupDef['label_ar'] ?? null,
                    'sort_order' => $groupDef['sort'] ?? 0,
                    'scope' => $newScope,
                ]
            );

            $shouldBeVisible = in_array($newScope, ['course', 'both'], true);
            $visibility = CourseAdminGroupVisibility::firstOrCreate(
                ['permission_group_id' => $group->permission_group_id],
                ['visible_to_course_admins' => $shouldBeVisible]
            );

            // When a group moves out of system-only scope, surface it to course admins.
            if ($previousScope === 'system' && $shouldBeVisible && ! $visibility->visible_to_course_admins) {
                $visibility->update(['visible_to_course_admins' => true]);
            }

            foreach ($groupDef['permissions'] ?? [] as $permKey => $permDef) {
                $configKeys[] = $permKey;
                $routes = $permDef['routes'] ?? [];
                $navKeys = $permDef['nav'] ?? [];
                $type = $permDef['type'] ?? 'both';

                Permission::updateOrCreate(
                    ['key' => $permKey],
                    [
                        'permission_group_id' => $group->permission_group_id,
                        'type' => $type,
                        'label_en' => $permDef['label_en'] ?? $permKey,
                        'label_ar' => $permDef['label_ar'] ?? null,
                        'description' => $permDef['description'] ?? null,
                        'route_names' => $routes,
                        'nav_key' => $navKeys[0] ?? null,
                        'is_system_only' => (bool) ($permDef['system_only'] ?? false),
                        'deprecated_at' => null,
                    ]
                );
            }
        }

        $orphaned = Permission::whereNotIn('key', $configKeys)
            ->whereNull('deprecated_at')
            ->get();

        foreach ($orphaned as $perm) {
            $perm->update(['deprecated_at' => now()]);
            $this->warn("Deprecated permission: {$perm->key}");
        }

        $unmapped = $this->findUnmappedRoutes($configKeys);

        $this->info('Synced '.count($configKeys).' permissions in '.count($catalog).' groups.');

        if ($unmapped->isNotEmpty()) {
            $this->warn('Unmapped named routes ('.$unmapped->count().'):');
            foreach ($unmapped->take(20) as $routeName) {
                $this->line("  - {$routeName}");
            }

            if ($this->option('check')) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function findUnmappedRoutes(array $configKeys): \Illuminate\Support\Collection
    {
        $mappedPatterns = collect();
        foreach (config('permissions', []) as $groupDef) {
            foreach ($groupDef['permissions'] ?? [] as $permDef) {
                foreach ($permDef['routes'] ?? [] as $pattern) {
                    $mappedPatterns->push($pattern);
                }
            }
        }

        $publicPrefixes = [
            'login', 'register', 'password.', 'otp.', 'locale.', 'theme.', 'sanctum.',
            'ignition.', 'verification.',
            'logout', 'home', 'dashboard',
            'profile', 'profile.',
            'account.',
            'notifications.',
            'help.',
            'calendar.',
            'my-learning.',
            'hubs.',
            'onboarding.',
            'application.',
            'communications.track-open',
            'courses.select',
        ];

        return collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->filter(function (string $name) use ($publicPrefixes) {
                foreach ($publicPrefixes as $prefix) {
                    if (Str::startsWith($name, $prefix)) {
                        return false;
                    }
                }

                return true;
            })
            ->filter(function (string $name) use ($mappedPatterns) {
                foreach ($mappedPatterns as $pattern) {
                    if (Str::is($pattern, $name)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }
}
