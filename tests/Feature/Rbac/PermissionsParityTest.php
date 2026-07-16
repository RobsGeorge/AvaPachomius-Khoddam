<?php

namespace Tests\Feature\Rbac;

use App\Models\Church;
use App\Models\Course;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Models\UserCourseRole;
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

    public function test_permissions_catalog_syncs(): void
    {
        Artisan::call('permissions:sync');
        $this->assertGreaterThan(50, Permission::count());
        $this->assertTrue(Permission::where('key', 'role.manage')->exists());
        $this->assertTrue(Permission::where('key', 'assignment.submit')->exists());
    }
}
