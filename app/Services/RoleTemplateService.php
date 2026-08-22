<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Church;
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
                'church_id' => $course->church_id,
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
                'church_id' => $service->church_id,
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
                'service.roster.status', 'service.progression.run',
                'service_application.review', 'service_application.form_builder',
                'people.view', 'people.create', 'people.import', 'people.invite', 'people.invite_bulk', 'people.place',
                'announcement.view', 'announcement.manage', 'announcement.publish',
                'communications.report',
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

    /**
     * Platform templates for church-wide roles (T3). Cloned into each church at
     * provisioning (T4). church_id stays null on the templates themselves.
     */
    public function ensureChurchTemplates(): Collection
    {
        $templates = [
            'church-admin' => [
                'church.configure', 'church.members.manage', 'church.registration_qr.manage', 'church.role.manage',
                'people.view', 'people.create', 'people.update', 'people.import',
                'people.invite', 'people.invite_bulk', 'people.place',
                'people.guardian_visibility.manage',
                'sacraments.view', 'sacraments.record',
                'documents.view', 'documents.upload',
                'priest.manage', 'priest.view',
                'confession.manage', 'confession.manage_delegated', 'confession.view', 'confession.book', 'confession.book_on_behalf',
                'appointment.manage', 'appointment.manage_delegated', 'appointment.view', 'appointment.book', 'appointment.book_on_behalf',
                'home_visit.manage', 'home_visit.view',
                'church.cycle.view', 'church.cycle.manage',
                'public_site.profile', 'public_site.theme',
                'public_site.manage', 'public_site.publish',
                'finance.payroll.manage', 'finance.payroll.view', 'finance.payroll.approve',
                'finance.money_in.manage', 'finance.money_in.view',
                'role.manage', 'user.assign_role',
                'announcement.view', 'announcement.manage', 'announcement.publish',
                'communications.report', 'roster.view', 'roster.announce',
                'service.view', 'service.manage',
            ],
            'priest' => [
                'priest.view',
                'people.guardian_visibility.manage',
                'sacraments.view', 'sacraments.record',
                'documents.view', 'documents.upload',
                'confession.manage', 'confession.view',
                'appointment.manage', 'appointment.view',
                'home_visit.manage', 'home_visit.view',
                'announcement.view',
                'roster.view',
            ],
            'secretary' => [
                'priest.view',
                'confession.view', 'confession.manage_delegated', 'confession.book_on_behalf',
                'appointment.view', 'appointment.manage_delegated', 'appointment.book_on_behalf',
                'announcement.view',
                'roster.view',
            ],
            'servant' => [
                'confession.view', 'confession.book',
                'appointment.view', 'appointment.book',
                'home_visit.manage', 'home_visit.view',
                'documents.view', 'documents.upload',
                'announcement.view',
                'roster.view',
            ],
        ];

        $roles = collect();

        foreach ($templates as $slug => $permissionKeys) {
            $role = Role::firstOrCreate(
                [
                    'slug' => $slug,
                    'course_id' => null,
                    'service_id' => null,
                    'church_id' => null,
                    'is_template' => true,
                ],
                [
                    'role_name' => match ($slug) {
                        'church-admin' => 'Church Admin',
                        'priest' => 'Priest',
                        'secretary' => 'Secretary',
                        default => 'Servant',
                    },
                    'role_decription' => $slug,
                    'description' => "Default {$slug} church template",
                    'is_system' => true,
                ]
            );

            $ids = Permission::whereIn('key', $permissionKeys)->pluck('permission_id');
            $role->permissions()->sync($ids);
            $roles->push($role);
        }

        return $roles;
    }

    /** @return array<string, Role> */
    public function cloneTemplatesIntoChurch(Church $church): array
    {
        $this->ensureChurchTemplates();

        // withoutTenancy: templates are null-church; clones target an arbitrary church
        // that may differ from the currently bound TenantContext (P1.2 / provisioning).
        $sourceRoles = Role::withoutTenancy()
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->whereNull('church_id')
            ->where('is_template', true)
            ->whereIn('slug', ['church-admin', 'priest', 'secretary', 'servant'])
            ->get();

        $enabledPermKeys = $this->permissionKeysForChurchCapabilities($church);
        $created = [];

        foreach ($sourceRoles as $template) {
            $existing = Role::withoutTenancy()
                ->where('church_id', $church->church_id)
                ->whereNull('course_id')
                ->whereNull('service_id')
                ->where('slug', $template->effectiveSlug())
                ->first();

            if ($existing) {
                $created[$existing->effectiveSlug()] = $existing;
                continue;
            }

            $role = Role::create([
                'role_name' => $template->role_name,
                'role_decription' => $template->role_decription,
                'slug' => $this->uniqueSlugForChurch($church->church_id, $template->effectiveSlug()),
                'description' => $template->description,
                'course_id' => null,
                'service_id' => null,
                'church_id' => $church->church_id,
                'is_system' => false,
                'is_template' => false,
                'cloned_from_role_id' => $template->role_id,
            ]);

            $templateKeys = $template->permissions()->pluck('permissions.key');
            $keys = $templateKeys->filter(
                fn (string $key) => $this->resolver->permissionAllowedByCapabilities($key, $church)
            );
            if ($template->effectiveSlug() === 'church-admin') {
                $keys = $keys->merge($enabledPermKeys)->unique();
            }

            $ids = Permission::whereIn('key', $keys)->pluck('permission_id');
            $role->permissions()->sync($ids);
            $created[$role->effectiveSlug()] = $role;
        }

        $this->resolver->bumpChurchPermissionsVersion($church);

        return $created;
    }

    public function uniqueSlugForChurch(int|string $churchId, string $slug): string
    {
        $base = Str::slug($slug) ?: 'role';
        $candidate = $base;
        $i = 1;

        while (
            Role::withoutTenancy()
                ->where('church_id', $churchId)
                ->whereNull('course_id')
                ->whereNull('service_id')
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Expand-only: attach any template keys missing on the church's cloned roles.
     * Does not remove permissions already on the clone.
     */
    public function mergeTemplatePermissionsIntoChurchClones(Church $church): int
    {
        $this->ensureChurchTemplates();

        $templates = Role::withoutTenancy()
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->whereNull('church_id')
            ->where('is_template', true)
            ->whereIn('slug', ['church-admin', 'priest', 'secretary', 'servant'])
            ->get()
            ->keyBy(fn (Role $r) => $r->effectiveSlug());

        $merged = 0;

        $clones = Role::withoutTenancy()
            ->where('church_id', $church->church_id)
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->where('is_template', false)
            ->get();

        foreach ($clones as $clone) {
            $slug = $clone->effectiveSlug();
            // church clones may use uniqueSlugForChurch → church-admin-1; match prefix
            $templateSlug = collect(['church-admin', 'priest', 'secretary', 'servant'])
                ->first(fn (string $s) => $slug === $s || str_starts_with($slug, $s.'-'));
            if (! $templateSlug || ! isset($templates[$templateSlug])) {
                continue;
            }

            $template = $templates[$templateSlug];
            $templateKeys = $template->permissions()->pluck('permissions.key');
            $keys = $templateKeys->filter(
                fn (string $key) => $this->resolver->permissionAllowedByCapabilities($key, $church)
            );
            if ($templateSlug === 'church-admin') {
                $keys = $keys->merge($this->permissionKeysForChurchCapabilities($church))->unique();
            }

            $existing = $clone->permissions()->pluck('permissions.key');
            $combined = $existing->merge($keys)->unique()->values();
            if ($combined->count() === $existing->count() && $combined->diff($existing)->isEmpty()) {
                continue;
            }

            $ids = Permission::whereIn('key', $combined)->pluck('permission_id');
            $clone->permissions()->sync($ids);
            $merged++;
        }

        if ($merged > 0) {
            $this->resolver->bumpChurchPermissionsVersion($church);
        }

        return $merged;
    }

    /**
     * Expand-only: merge service-admin / service-member template keys onto service clones.
     */
    public function mergeTemplatePermissionsIntoServiceClones(): int
    {
        $this->ensureServiceTemplates();

        $templates = Role::query()
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->where('is_template', true)
            ->whereIn('slug', ['service-admin', 'service-member'])
            ->get()
            ->keyBy('slug');

        $merged = 0;
        $clones = Role::query()
            ->whereNotNull('service_id')
            ->where('is_template', false)
            ->get()
            ->filter(function (Role $clone) {
                $slug = $clone->effectiveSlug();

                return $slug === 'service-admin'
                    || $slug === 'service-member'
                    || str_starts_with($slug, 'service-admin-')
                    || str_starts_with($slug, 'service-member-');
            });

        foreach ($clones as $clone) {
            $slug = $clone->effectiveSlug();
            $templateSlug = str_starts_with($slug, 'service-admin') ? 'service-admin' : 'service-member';
            if (! isset($templates[$templateSlug])) {
                continue;
            }

            $templateKeys = $templates[$templateSlug]->permissions()->pluck('permissions.key');
            $existing = $clone->permissions()->pluck('permissions.key');
            $missing = $templateKeys->diff($existing);
            if ($missing->isEmpty()) {
                continue;
            }

            $combined = $existing->merge($templateKeys)->unique()->values();
            $ids = Permission::whereIn('key', $combined)->pluck('permission_id');
            $clone->permissions()->sync($ids);
            $merged++;
        }

        return $merged;
    }

    private function permissionKeysForChurchCapabilities(Church $church): Collection
    {
        $keys = collect();
        foreach ((array) config('capabilities') as $capabilityKey => $def) {
            if (! $church->hasCapability($capabilityKey)) {
                continue;
            }
            $keys = $keys->merge((array) ($def['permissions'] ?? []));
        }

        return $keys->unique()->values();
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
            'project.view', 'project.manage',
            'exam.view', 'exam.author', 'exam.schedule', 'exam.grade',
            'grade.view', 'grade.manage',
            'attendance.record', 'attendance.view_all', 'attendance.report', 'attendance.edit',
            'announcement.view', 'announcement.manage', 'announcement.publish',
            'communications.report',
            'roster.view', 'roster.announce', 'session.notify',
            'graduation.view', 'graduation.configure', 'course.close', 'certificate.manage',
            'feedback.view', 'feedback.manage', 'feedback.report', 'feedback.identity.request',
            'student_assessment.view', 'student_assessment.manage',
            'student_notes.view', 'student_notes.manage',
            'live_quiz.play', 'live_quiz.host', 'live_quiz.manage',
            'events.view', 'events.reserve',
        ];
    }

    private function studentPermissions(): array
    {
        return [
            'course.view', 'course.access',
            'curriculum.view', 'assignment.view', 'assignment.submit',
            'project.view', 'project.join',
            'exam.view', 'exam.take',
            'grade.view', 'certificate.download',
            'attendance.view_own',
            'announcement.view',
            'feedback.view', 'live_quiz.play',
            'events.view', 'events.reserve',
        ];
    }
}
