<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleTemplateService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    /** @return array<string, Role> */
    public function cloneTemplatesIntoCourse(Course $course, ?int $sourceCourseId = null): array
    {
        $sourceRoles = $sourceCourseId
            ? Role::where('course_id', $sourceCourseId)->get()
            : Role::whereNull('course_id')->where('is_template', true)->get();

        $created = [];

        foreach ($sourceRoles as $template) {
            $role = Role::create([
                'role_name' => $template->role_name,
                'role_decription' => $template->role_decription,
                'slug' => $this->uniqueSlugForCourse($course->course_id, $template->effectiveSlug()),
                'description' => $template->description,
                'course_id' => $course->course_id,
                'is_system' => false,
                'is_template' => false,
                'cloned_from_role_id' => $template->role_id,
            ]);

            $permissionIds = $template->permissions()->pluck('permissions.permission_id');
            $role->permissions()->sync($permissionIds);
            $created[$role->effectiveSlug()] = $role;
        }

        $course->update([
            'roles_cloned_from_course_id' => $sourceCourseId,
        ]);

        $this->resolver->bumpCoursePermissionsVersion($course);

        return $created;
    }

    public function copyRolesFromCourse(Course $target, Course $source): array
    {
        return $this->cloneTemplatesIntoCourse($target, $source->course_id);
    }

    public function ensureSystemTemplates(): Collection
    {
        $templates = [
            'admin' => $this->adminPermissions(),
            'instructor' => $this->instructorPermissions(),
            'student' => $this->studentPermissions(),
        ];

        $roles = collect();

        foreach ($templates as $slug => $permissionKeys) {
            $role = Role::firstOrCreate(
                ['slug' => $slug, 'course_id' => null, 'is_template' => true],
                [
                    'role_name' => ucfirst($slug),
                    'role_decription' => ucfirst($slug),
                    'description' => "Default {$slug} template",
                    'is_system' => true,
                ]
            );

            $ids = Permission::whereIn('key', $permissionKeys)->pluck('permission_id');
            $role->permissions()->sync($ids);
            $roles->push($role);
        }

        return $roles;
    }

    public function uniqueSlugForCourse(string $courseId, string $slug): string
    {
        $base = Str::slug($slug) ?: 'role';
        $candidate = $base;
        $i = 1;

        while (Role::where('course_id', $courseId)->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /** @return array<string, Role> */
    public function cloneTemplatesIntoService(\App\Models\ChurchService $service, ?int $sourceServiceId = null): array
    {
        $sourceRoles = $sourceServiceId
            ? Role::forService($sourceServiceId)->get()
            : Role::query()
                ->whereNull('course_id')
                ->whereNull('service_id')
                ->where('is_template', true)
                ->whereIn('slug', ['service-admin', 'service-member'])
                ->get();

        if ($sourceRoles->isEmpty() && ! $sourceServiceId) {
            $this->ensureServiceTemplates();
            $sourceRoles = Role::query()
                ->whereNull('course_id')
                ->whereNull('service_id')
                ->where('is_template', true)
                ->whereIn('slug', ['service-admin', 'service-member'])
                ->get();
        }

        $created = [];

        foreach ($sourceRoles as $template) {
            $role = Role::create([
                'role_name' => $template->role_name,
                'role_decription' => $template->role_decription,
                'slug' => $this->uniqueSlugForService($service->service_id, $template->effectiveSlug()),
                'description' => $template->description,
                'course_id' => null,
                'service_id' => $service->service_id,
                'is_system' => false,
                'is_template' => false,
                'cloned_from_role_id' => $template->role_id,
            ]);

            $permissionIds = $template->permissions()
                ->whereHas('group', fn ($q) => $q->whereIn('scope', ['service', 'both', 'system']))
                ->pluck('permissions.permission_id');
            $role->permissions()->sync($permissionIds);
            $created[$role->effectiveSlug()] = $role;
        }

        $service->bumpPermissionsVersion();

        return $created;
    }

    public function ensureServiceTemplates(): Collection
    {
        $templates = [
            'service-admin' => [
                'service.view', 'service.manage',
                'service.member.add', 'service.member.remove', 'service.member.add_cross',
                'service.role.manage', 'service.user.assign_role',
                'service_application.review', 'service_application.form_builder',
                'announcement.view', 'announcement.manage', 'announcement.publish',
                'roster.view',
            ],
            'service-member' => [
                'service.view',
                'announcement.view',
            ],
        ];

        $roles = collect();

        foreach ($templates as $slug => $permissionKeys) {
            $role = Role::firstOrCreate(
                [
                    'slug' => $slug,
                    'course_id' => null,
                    'service_id' => null,
                    'is_template' => true,
                ],
                [
                    'role_name' => $slug === 'service-admin' ? 'Service Admin' : 'Service Member',
                    'role_decription' => $slug,
                    'description' => "Default {$slug} template",
                    'is_system' => true,
                ]
            );

            $ids = Permission::whereIn('key', $permissionKeys)->pluck('permission_id');
            $role->permissions()->sync($ids);
            $roles->push($role);
        }

        return $roles;
    }

    public function uniqueSlugForService(int|string $serviceId, string $slug): string
    {
        $base = Str::slug($slug) ?: 'role';
        $candidate = $base;
        $i = 1;

        while (Role::where('service_id', $serviceId)->whereNull('course_id')->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function adminPermissions(): array
    {
        return Permission::where('is_system_only', false)
            ->whereHas('group', fn ($q) => $q->whereIn('scope', ['course', 'both']))
            ->pluck('key')
            ->all();
    }

    private function instructorPermissions(): array
    {
        return [
            'course.access', 'curriculum.view', 'curriculum.manage',
            'assignment.view', 'assignment.manage', 'assignment.grade',
            'exam.view', 'exam.author', 'exam.schedule', 'exam.grade',
            'grade.view', 'grade.manage',
            'attendance.record', 'attendance.view_all', 'attendance.report', 'attendance.edit',
            'announcement.view', 'announcement.manage', 'announcement.publish',
            'roster.view', 'roster.announce', 'session.notify',
            'graduation.view', 'graduation.configure', 'course.close', 'certificate.manage',
            'feedback.view', 'feedback.manage', 'feedback.report',
            'live_quiz.play', 'live_quiz.host', 'live_quiz.manage',
            'events.view', 'events.reserve',
        ];
    }

    private function studentPermissions(): array
    {
        return [
            'course.view', 'course.access',
            'curriculum.view', 'assignment.view', 'assignment.submit',
            'exam.view', 'exam.take',
            'grade.view', 'certificate.download',
            'attendance.view_own',
            'announcement.view',
            'feedback.view', 'live_quiz.play',
            'events.view', 'events.reserve',
        ];
    }
}
