<?php

namespace Tests\Feature\Rbac;

use App\Models\Church;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Services\CoursePermissionResolver;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * Permissions parity: seeded template personas keep the same allow/deny surface
 * as the admin / instructor / student templates (no role-name string authz).
 */
class PermissionsParityTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_template_personas_match_permission_matrix(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();

        $course = $this->createCourse(['title' => 'Parity Course']);
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $admin = $this->createUser(['email' => 'parity-admin@example.com']);
        $instructor = $this->createUser(['email' => 'parity-instr@example.com']);
        $student = $this->createUser(['email' => 'parity-student@example.com']);

        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($instructor, $course, $roles['instructor']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $resolver = app(CoursePermissionResolver::class);

        // Admin: role.manage + staff writes
        $this->assertTrue($resolver->canInCourse($admin, 'role.manage', $course));
        $this->assertTrue($resolver->canInCourse($admin, 'assignment.manage', $course));
        $this->assertTrue($admin->isAdmin((string) $course->course_id));
        $this->assertTrue($admin->isInstructorOrAdmin((string) $course->course_id));
        $this->assertFalse($admin->isStudent((string) $course->course_id));

        // Instructor: staff writes, no role.manage
        $this->assertFalse($resolver->canInCourse($instructor, 'role.manage', $course));
        $this->assertTrue($resolver->canInCourse($instructor, 'assignment.manage', $course));
        $this->assertTrue($resolver->canInCourse($instructor, 'attendance.record', $course));
        $this->assertTrue($instructor->isInstructorOrAdmin((string) $course->course_id));
        $this->assertFalse($instructor->isAdmin((string) $course->course_id));
        $this->assertFalse($instructor->isStudent((string) $course->course_id));

        // Student: learner keys only
        $this->assertTrue($resolver->canInCourse($student, 'assignment.submit', $course));
        $this->assertTrue($resolver->canInCourse($student, 'exam.take', $course));
        $this->assertFalse($resolver->canInCourse($student, 'assignment.manage', $course));
        $this->assertFalse($resolver->canInCourse($student, 'attendance.record', $course));
        $this->assertTrue($student->isStudent((string) $course->course_id));
        $this->assertFalse($student->isInstructorOrAdmin((string) $course->course_id));
    }

    public function test_church_role_grants_flow_into_course_hierarchy(): void
    {
        $church = Church::main();
        TenantContext::set($church);

        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $course = $this->createCourse([
            'title' => 'Hierarchy Course',
            'church_id' => $church->church_id,
        ]);

        $churchAdmin = $this->createUser(['email' => 'hier-ca@example.com']);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $churchAdmin->user_id,
            'role_id' => $roles['church-admin']->role_id,
            'assigned_at' => now(),
        ]);

        // No user_course_role — access only via church → course walk.
        $this->assertSame(
            0,
            UserCourseRole::where('user_id', $churchAdmin->user_id)
                ->where('course_id', $course->course_id)
                ->count()
        );

        $resolver = app(CoursePermissionResolver::class);
        $churchKeys = $roles['church-admin']->permissions()->pluck('key');

        if ($churchKeys->contains('role.manage')) {
            $this->assertTrue($resolver->canInCourse($churchAdmin, 'role.manage', $course));
        }

        // Course-only grants must not leak to another course.
        $other = $this->createCourse(['title' => 'Other Course', 'church_id' => $church->church_id]);
        $scoped = $this->createUser(['email' => 'hier-scoped@example.com']);
        $courseRole = $this->courseRoleWithPermissions($other, 'grader-only', ['exam.grade']);
        $this->assignCourseRole($scoped, $other, $courseRole);

        $this->assertTrue($resolver->canInCourse($scoped, 'exam.grade', $other));
        $this->assertFalse($resolver->canInCourse($scoped, 'exam.grade', $course));
    }

    public function test_service_role_grants_flow_into_course_hierarchy(): void
    {
        Artisan::call('permissions:sync');
        app(RoleTemplateService::class)->ensureServiceTemplates();

        $service = $this->createService(['title' => 'Hierarchy Service']);
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoService($service);
        $course = $this->createCourse([
            'title' => 'Service Hier Course',
            'service_id' => $service->service_id,
        ]);

        $svcAdmin = $this->createUser(['email' => 'svc-hier@example.com']);
        $serviceAdminRole = $roles['service-admin'] ?? collect($roles)->first(
            fn (Role $role) => str_starts_with($role->effectiveSlug(), 'service-admin')
        );
        $this->assertNotNull($serviceAdminRole);
        UserServiceRole::create([
            'service_id' => $service->service_id,
            'user_id' => $svcAdmin->user_id,
            'role_id' => $serviceAdminRole->role_id,
            'is_primary' => true,
        ]);

        $this->assertSame(
            0,
            UserCourseRole::where('user_id', $svcAdmin->user_id)
                ->where('course_id', $course->course_id)
                ->count()
        );

        $resolver = app(CoursePermissionResolver::class);
        // Service clones keep service/both-scoped keys; those must still resolve in course context.
        $held = $resolver->permissionsInCourse($svcAdmin, $course);
        $this->assertTrue(
            $held->contains('service.manage'),
            'Expected service.manage via service→course hierarchy; held=['.$held->implode(',').']'
        );
        $this->assertTrue($resolver->canInCourse($svcAdmin, 'service.manage', $course));
        $this->assertTrue($resolver->canInCourse($svcAdmin, 'service.view', $course));
    }

    public function test_roster_role_ids_use_permission_keys_not_role_name(): void
    {
        Artisan::call('permissions:sync');
        app(RoleTemplateService::class)->ensureSystemTemplates();

        $course = $this->createCourse(['title' => 'Roster Keys']);
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $studentIds = Role::studentRoleIds();
        $staffIds = Role::staffRoleIds();

        $this->assertTrue($studentIds->contains($roles['student']->role_id));
        $this->assertFalse($studentIds->contains($roles['admin']->role_id));
        $this->assertTrue($staffIds->contains($roles['admin']->role_id));
        $this->assertTrue($staffIds->contains($roles['instructor']->role_id));
        $this->assertFalse($staffIds->contains($roles['student']->role_id));
    }

    public function test_roster_role_ids_keep_unsynced_slug_roles_when_learner_catalog_exists(): void
    {
        Artisan::call('permissions:sync');
        app(RoleTemplateService::class)->ensureSystemTemplates();

        // Seed at least one permission-backed learner role so the catalog path is active.
        $syncedCourse = $this->createCourse(['title' => 'Synced Roster']);
        $synced = app(RoleTemplateService::class)->cloneTemplatesIntoCourse($syncedCourse);

        $legacyCourse = $this->createCourse(['title' => 'Legacy Slug Roster']);
        $legacyStudent = Role::create([
            'role_name' => 'Student',
            'role_decription' => 'unsynced student',
            'slug' => 'student',
            'course_id' => $legacyCourse->course_id,
            'church_id' => $legacyCourse->church_id,
            'is_template' => false,
        ]);
        $legacyAdmin = Role::create([
            'role_name' => 'Admin',
            'role_decription' => 'unsynced admin',
            'slug' => 'admin',
            'course_id' => $legacyCourse->course_id,
            'church_id' => $legacyCourse->church_id,
            'is_template' => false,
        ]);

        $this->assertSame(0, $legacyStudent->permissions()->count());
        $this->assertSame(0, $legacyAdmin->permissions()->count());

        $studentIds = Role::studentRoleIds();
        $staffIds = Role::staffRoleIds();

        $this->assertTrue($studentIds->contains($synced['student']->role_id));
        $this->assertTrue(
            $studentIds->contains($legacyStudent->role_id),
            'Unsynced slug=student roles must stay in roster IDs once any learner permissions exist'
        );
        $this->assertFalse($studentIds->contains($legacyAdmin->role_id));

        $this->assertTrue($staffIds->contains($synced['admin']->role_id));
        $this->assertTrue(
            $staffIds->contains($legacyAdmin->role_id),
            'Unsynced slug=admin roles must stay in roster IDs once any staff permissions exist'
        );
        $this->assertFalse($staffIds->contains($legacyStudent->role_id));

        $resolved = Role::studentRoleForCourse($legacyCourse->course_id);
        $this->assertNotNull($resolved);
        $this->assertSame($legacyStudent->role_id, $resolved->role_id);
    }

    public function test_permissions_catalog_syncs(): void
    {
        Artisan::call('permissions:sync');

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
        $unmapped = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->filter(function (string $name) use ($publicPrefixes) {
                foreach ($publicPrefixes as $prefix) {
                    if (\Illuminate\Support\Str::startsWith($name, $prefix)) {
                        return false;
                    }
                }

                return true;
            })
            ->filter(function (string $name) use ($mappedPatterns) {
                foreach ($mappedPatterns as $pattern) {
                    if (\Illuminate\Support\Str::is($pattern, $name)) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->sort()
            ->values();

        $this->assertSame(
            [],
            $unmapped->all(),
            'Unmapped named routes (attach in config/permissions.php): '.$unmapped->implode(', ')
        );

        $this->assertGreaterThan(50, Permission::count());
        $this->assertTrue(Permission::where('key', 'role.manage')->exists());
        $this->assertTrue(Permission::where('key', 'assignment.submit')->exists());
    }
}
