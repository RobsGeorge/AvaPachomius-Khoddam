<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseAdminGroupVisibility;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\CoursePermissionResolver;
use App\Services\RoleTemplateService;
use App\Support\NavigationHub;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EventModuleTestCase;

class DynamicRoleManagementTest extends EventModuleTestCase
{
    public function test_superadmin_bypasses_permission_checks(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);
        $course = $this->createCourse();

        $this->assertTrue($super->canInCourse('exam.grade', $course));
        $this->assertTrue($super->canInSystem('translation.manage'));
    }

    public function test_course_scoped_grant_only_applies_in_that_course(): void
    {
        $user = $this->createUser();
        $courseA = $this->createCourse(['title' => 'A']);
        $courseB = $this->createCourse(['title' => 'B']);

        $roleA = $this->courseRoleWithPermissions($courseA, 'grader', ['exam.grade']);
        $roleB = $this->courseRoleWithPermissions($courseB, 'viewer', ['exam.view']);

        $this->assignCourseRole($user, $courseA, $roleA);
        UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $courseB->course_id,
            'role_id' => $roleB->role_id,
        ]);

        $this->assertTrue($user->canInCourse('exam.grade', $courseA));
        $this->assertFalse($user->canInCourse('exam.grade', $courseB));
    }

    public function test_one_role_per_user_per_course_on_assignment(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role1 = $this->courseRoleWithPermissions($course, 'r1', ['exam.view']);
        $role2 = $this->courseRoleWithPermissions($course, 'r2', ['exam.grade']);

        $this->assignCourseRole($user, $course, $role1);

        UserCourseRole::updateOrCreate(
            ['user_id' => $user->user_id, 'course_id' => $course->course_id],
            ['role_id' => $role2->role_id]
        );

        $this->assertEquals(1, UserCourseRole::where('user_id', $user->user_id)->where('course_id', $course->course_id)->count());
        $this->assertEquals($role2->role_id, UserCourseRole::where('user_id', $user->user_id)->where('course_id', $course->course_id)->value('role_id'));
    }

    public function test_matrix_persist_syncs_role_permission(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $course = $this->createCourse();
        $role = Role::create([
            'role_name' => 'Custom',
            'role_decription' => 'Custom',
            'slug' => 'custom',
            'course_id' => $course->course_id,
        ]);

        $permIds = Permission::whereIn('key', ['exam.grade', 'exam.view'])->pluck('permission_id')->all();

        $this->actingAs($admin)
            ->put(route('courses.roles.update', [$course, $role]), [
                'role_name' => 'Custom',
                'permissions' => $permIds,
            ])
            ->assertRedirect(route('courses.roles.index', $course));

        $this->assertEquals(2, $role->fresh()->permissions()->count());
    }

    public function test_template_clone_creates_course_roles(): void
    {
        $course = $this->createCourse();
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->assertGreaterThanOrEqual(3, Role::where('course_id', $course->course_id)->count());
    }

    public function test_copy_roles_from_another_course(): void
    {
        $source = $this->createCourse(['title' => 'Source']);
        $target = $this->createCourse(['title' => 'Target']);

        $this->courseRoleWithPermissions($source, 'examiner', ['exam.grade', 'exam.view']);

        app(RoleTemplateService::class)->copyRolesFromCourse($target, $source);

        $this->assertTrue(Role::where('course_id', $target->course_id)->where('slug', 'examiner')->exists());
    }

    public function test_hidden_group_not_visible_to_course_admin_matrix(): void
    {
        $group = PermissionGroup::where('group_key', 'exams')->first();
        CourseAdminGroupVisibility::updateOrCreate(
            ['permission_group_id' => $group->permission_group_id],
            ['visible_to_course_admins' => false]
        );

        $visible = app(\App\Policies\RolePermissionPolicy::class)->visibleGroupsForCourseAdmin();

        $this->assertFalse($visible->contains('group_key', 'exams'));
    }

    public function test_system_permission_not_available_via_course_role(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'bad', ['translation.manage']);

        $this->assignCourseRole($user, $course, $role);

        $this->assertFalse($user->canInSystem('translation.manage'));
    }

    public function test_staff_archived_removes_staff_permissions(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'instructor', ['exam.author', 'curriculum.manage']);

        $ucr = UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $course->course_id,
            'role_id' => $role->role_id,
            'staff_archived_at' => now(),
        ]);

        $resolver = app(CoursePermissionResolver::class);
        $perms = $resolver->permissionsInCourse($user, $course);

        $this->assertFalse($perms->contains('exam.author'));
    }

    public function test_permissions_sync_command_succeeds(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->assertGreaterThan(20, Permission::count());
    }

    public function test_nav_hides_exam_manage_without_permission(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view', 'exam.take']);
        $this->assignCourseRole($user, $course, $role);

        $urls = collect(NavigationHub::academicLinks($user))->pluck('url');
        $manageUrl = route('exams.dashboard');

        $this->assertFalse($urls->contains($manageUrl));
    }

    public function test_delete_role_in_use_is_blocked(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'used', ['role.manage']);
        $this->assignCourseRole($user, $course, $role);

        $this->actingAs($admin)
            ->delete(route('courses.roles.destroy', [$course, $role]))
            ->assertStatus(422);
    }

    public function test_course_role_assignment_can_be_removed_via_course_roles_page(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'instructor', ['curriculum.manage']);

        $assignment = UserCourseRole::updateOrCreate(
            ['user_id' => $user->user_id, 'course_id' => $course->course_id],
            ['role_id' => $role->role_id]
        );

        $this->actingAs($admin)
            ->delete(route('courses.roles.assignments.destroy', [$course, $assignment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_course_role', [
            'user_course_role_id' => $assignment->user_course_role_id,
        ]);
    }

    public function test_superadmin_role_picker_only_lists_course_scoped_roles(): void
    {
        $super = $this->createUser(['is_superadmin' => true]);
        $courseA = $this->createCourse(['title' => 'Alpha']);
        $courseB = $this->createCourse(['title' => 'Beta']);

        Role::create([
            'role_name' => 'Legacy Admin',
            'role_decription' => 'Legacy',
            'slug' => 'legacy-admin',
            'course_id' => null,
            'is_template' => false,
        ]);

        $this->courseRoleWithPermissions($courseA, 'admin', ['role.manage']);
        $this->courseRoleWithPermissions($courseB, 'admin', ['role.manage']);

        $response = $this->actingAs($super)->get(route('superadmin.course-roles'));

        $response->assertOk();
        $response->assertSee('data-course-id="'.$courseA->course_id.'"', false);
        $response->assertSee('data-course-id="'.$courseB->course_id.'"', false);
        $response->assertSee('Legacy Admin', false);
    }

    public function test_bump_course_permissions_version_clears_current_and_next_cache_keys(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'viewer', ['exam.view']);
        $this->assignCourseRole($user, $course, $role);

        $resolver = app(CoursePermissionResolver::class);
        $resolver->permissionsInCourse($user, $course->fresh());

        $version = (int) $course->fresh()->permissions_version;
        $cacheKey = "perms:{$course->course_id}:{$user->user_id}:{$version}";
        $this->assertTrue(Cache::has($cacheKey));

        $role->permissions()->sync(
            Permission::where('key', 'exam.grade')->pluck('permission_id')
        );

        $staleCourse = Course::find($course->course_id);
        $resolver->bumpCoursePermissionsVersion($staleCourse);

        $course->refresh();
        $newVersion = (int) $course->permissions_version;
        $newCacheKey = "perms:{$course->course_id}:{$user->user_id}:{$newVersion}";

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($newCacheKey));

        $permissions = $resolver->permissionsInCourse($user, $course->fresh());
        $this->assertTrue($permissions->contains('exam.grade'));
        $this->assertFalse($permissions->contains('exam.view'));
    }
}
