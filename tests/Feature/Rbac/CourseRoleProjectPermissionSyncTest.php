<?php

namespace Tests\Feature\Rbac;

use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ProjectAccessRepairService;
use App\Services\RoleTemplateService;
use App\Support\NavigationHub;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class CourseRoleProjectPermissionSyncTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_repair_merges_project_keys_onto_stale_course_clones(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Stale Project Roles', 'status' => 'active']);
        $cloned = $templates->cloneTemplatesIntoCourse($course);
        $studentRole = $cloned['student'] ?? null;
        $instructorRole = $cloned['instructor'] ?? null;
        $this->assertNotNull($studentRole);
        $this->assertNotNull($instructorRole);

        $projectIds = Permission::whereIn('key', [
            'project.view', 'project.join', 'project.manage', 'project.grade',
        ])->pluck('permission_id');
        $this->assertNotEmpty($projectIds);
        $studentRole->permissions()->detach($projectIds);
        $instructorRole->permissions()->detach($projectIds);

        $this->assertFalse($studentRole->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertFalse($instructorRole->fresh()->permissions()->where('permissions.key', 'project.manage')->exists());

        $merged = app(ProjectAccessRepairService::class)->repair();
        $this->assertGreaterThan(0, $merged);

        $this->assertTrue($studentRole->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($studentRole->fresh()->permissions()->where('permissions.key', 'project.join')->exists());
        $this->assertTrue($instructorRole->fresh()->permissions()->where('permissions.key', 'project.manage')->exists());
    }

    public function test_stale_student_and_admin_regain_project_nav_and_pages(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Hidden Projects Course', 'status' => 'active']);
        $cloned = $templates->cloneTemplatesIntoCourse($course);

        $projectIds = Permission::whereIn('key', [
            'project.view', 'project.join', 'project.manage', 'project.grade',
        ])->pluck('permission_id');
        $cloned['student']->permissions()->detach($projectIds);
        $cloned['instructor']->permissions()->detach($projectIds);

        $student = $this->createUser(['email' => 'stale-prj-student@example.com']);
        $admin = $this->createUser(['email' => 'stale-prj-admin@example.com']);
        $this->assignCourseRole($student, $course, $cloned['student']);
        $this->assignCourseRole($admin, $course, $cloned['instructor']);

        $studentUrls = collect(NavigationHub::academicLinks($student))->pluck('url');
        $this->assertFalse($studentUrls->contains(route('projects.index')));

        $this->actingAs($student)->get(route('projects.index'))->assertForbidden();
        // Staff may still see Manage in nav via the legacy instructor fallback,
        // but the page 403s until project.manage is merged onto the clone.
        $this->actingAs($admin)->get(route('projects.manage'))->assertForbidden();

        app(ProjectAccessRepairService::class)->repair();
        $student->unsetRelation('userCourseRoles');
        $admin->unsetRelation('userCourseRoles');

        $studentUrls = collect(NavigationHub::academicLinks($student->fresh()))->pluck('url');
        $this->assertTrue($studentUrls->contains(route('projects.index')));

        $adminUrls = collect(NavigationHub::academicLinks($admin->fresh()))->pluck('url');
        $this->assertTrue($adminUrls->contains(route('projects.manage')));

        $this->actingAs($student->fresh())->get(route('projects.index'))->assertOk();
        $this->actingAs($admin->fresh())->get(route('projects.manage'))->assertOk();
    }

    public function test_custom_learner_role_with_assignments_gains_project_keys(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Custom Learner Course', 'status' => 'active']);

        $learner = $this->courseRoleWithPermissions($course, 'servant-learner', [
            'assignment.view', 'assignment.submit', 'exam.view', 'exam.take',
        ]);
        $this->assertFalse($learner->fresh()->permissions()->where('permissions.key', 'project.view')->exists());

        $student = $this->createUser(['email' => 'custom-learner-prj@example.com']);
        $this->assignCourseRole($student, $course, $learner);

        $urls = collect(NavigationHub::academicLinks($student))->pluck('url');
        $this->assertFalse($urls->contains(route('projects.index')));

        $merged = app(ProjectAccessRepairService::class)->repair();
        $this->assertGreaterThan(0, $merged);

        $this->assertTrue($learner->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($learner->fresh()->permissions()->where('permissions.key', 'project.join')->exists());

        $student->unsetRelation('userCourseRoles');
        $urls = collect(NavigationHub::academicLinks($student->fresh()))->pluck('url');
        $this->assertTrue($urls->contains(route('projects.index')));
        $this->actingAs($student->fresh())->get(route('projects.index'))->assertOk();
    }

    public function test_copying_roles_from_a_stale_course_restores_project_keys(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $source = $this->createCourse(['title' => 'Stale Source', 'status' => 'active']);
        $cloned = $templates->cloneTemplatesIntoCourse($source);
        $projectIds = Permission::whereIn('key', [
            'project.view', 'project.join', 'project.manage', 'project.grade',
        ])->pluck('permission_id');
        $cloned['student']->permissions()->detach($projectIds);

        $target = $this->createCourse(['title' => 'Copied Target', 'status' => 'active']);
        $templates->copyRolesFromCourse($target, $source);

        $copied = Role::query()
            ->where('course_id', $target->course_id)
            ->where('slug', $cloned['student']->slug)
            ->first();
        $this->assertNotNull($copied);
        $this->assertTrue($copied->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($copied->permissions()->where('permissions.key', 'project.join')->exists());
    }

    public function test_enrolled_custom_role_without_assignment_keys_gains_project_nav(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Custom Cohort Course', 'status' => 'active']);

        $cohort = $this->courseRoleWithPermissions($course, 'cohort-a', []);
        $this->assertFalse($cohort->fresh()->permissions()->where('permissions.key', 'project.view')->exists());

        $student = $this->createUser(['email' => 'cohort-learner-prj@example.com']);
        $this->assignCourseRole($student, $course, $cohort);

        $urls = collect(NavigationHub::academicLinks($student))->pluck('url');
        $this->assertFalse($urls->contains(route('projects.index')));

        $merged = app(ProjectAccessRepairService::class)->repair();
        $this->assertGreaterThan(0, $merged);

        $this->assertTrue($cohort->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($cohort->fresh()->permissions()->where('permissions.key', 'project.join')->exists());

        $student->unsetRelation('userCourseRoles');
        $urls = collect(NavigationHub::academicLinks($student->fresh()))->pluck('url');
        $this->assertTrue($urls->contains(route('projects.index')));
        $this->actingAs($student->fresh())->get(route('projects.index'))->assertOk();
    }

    public function test_exam_only_learner_role_gains_project_keys(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Exam Only Course', 'status' => 'active']);

        $learner = $this->courseRoleWithPermissions($course, 'exam-learner', [
            'exam.view', 'exam.take', 'course.access',
        ]);
        $this->assertFalse($learner->fresh()->permissions()->where('permissions.key', 'project.view')->exists());

        $merged = app(ProjectAccessRepairService::class)->repair();
        $this->assertGreaterThan(0, $merged);

        $this->assertTrue($learner->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($learner->fresh()->permissions()->where('permissions.key', 'project.join')->exists());
    }

    public function test_servant_course_clone_gains_project_keys(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Servant Prep Course', 'status' => 'active']);

        $servant = $this->courseRoleWithPermissions($course, 'servant', [
            'course.access',
        ]);
        $this->assertFalse($servant->fresh()->permissions()->where('permissions.key', 'project.view')->exists());

        $student = $this->createUser(['email' => 'servant-learner-prj@example.com']);
        $this->assignCourseRole($student, $course, $servant);

        $urls = collect(NavigationHub::academicLinks($student))->pluck('url');
        $this->assertFalse($urls->contains(route('projects.index')));

        app(ProjectAccessRepairService::class)->repair();

        $this->assertTrue($servant->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertTrue($servant->fresh()->permissions()->where('permissions.key', 'project.join')->exists());

        $student->unsetRelation('userCourseRoles');
        $urls = collect(NavigationHub::academicLinks($student->fresh()))->pluck('url');
        $this->assertTrue($urls->contains(route('projects.index')));
        $this->actingAs($student->fresh())->get(route('projects.index'))->assertOk();
    }

    public function test_unused_non_learner_course_role_does_not_gain_project_keys(): void
    {
        Artisan::call('permissions:sync');
        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        $church = Church::main();
        TenantContext::set($church);
        $course = $this->createCourse(['title' => 'Unused Priest Role Course', 'status' => 'active']);

        $priest = $this->courseRoleWithPermissions($course, 'priest', []);
        $this->assertFalse($priest->fresh()->permissions()->where('permissions.key', 'project.view')->exists());

        app(ProjectAccessRepairService::class)->repair();

        $this->assertFalse($priest->fresh()->permissions()->where('permissions.key', 'project.view')->exists());
        $this->assertFalse($priest->fresh()->permissions()->where('permissions.key', 'project.join')->exists());
    }
}
