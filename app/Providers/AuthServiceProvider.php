<?php

namespace App\Providers;

use App\Models\Permission;
use App\Services\CoursePermissionResolver;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Role::class => \App\Policies\RolePermissionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user->is_superadmin ?? false) {
                return true;
            }

            return null;
        });

        try {
            $keys = Permission::pluck('key')->all();
        } catch (\Throwable) {
            $keys = collect(config('permissions', []))
                ->flatMap(fn ($g) => array_keys($g['permissions'] ?? []))
                ->all();
        }

        $resolver = app(CoursePermissionResolver::class);

        foreach ($keys as $key) {
            Gate::define($key, function ($user, ...$args) use ($resolver, $key) {
                $course = $args[0] ?? current_course();

                if ($course instanceof \App\Models\Course) {
                    return $resolver->canInCourse($user, $key, $course);
                }

                if ($resolver->canInSystem($user, $key)) {
                    return true;
                }

                return $resolver->hasCourseAccess($user, $course ?? '')
                    && $user->userCourseRoles()
                        ->whereNull('staff_archived_at')
                        ->get()
                        ->contains(function ($ucr) use ($resolver, $key, $user) {
                            $c = \App\Models\Course::find($ucr->course_id);

                            return $c && $resolver->canInCourse($user, $key, $c);
                        });
            });
        }
    }
}
