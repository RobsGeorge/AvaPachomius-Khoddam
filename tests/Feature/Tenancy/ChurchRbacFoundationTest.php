<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Role;
use App\Models\UserCourseRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * T3-expand — RBAC is anchored to churches by additive, dormant church_id columns on
 * roles + user_course_role, backfilled to Tenant Zero, plus church.permissions_version
 * for future cache busting. Nothing reads these yet (church-contextual resolution is
 * T3-enforce); this only guards the schema foundation.
 */
class ChurchRbacFoundationTest extends EventModuleTestCase
{
    public function test_rbac_tables_have_a_church_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('roles', 'church_id'));
        $this->assertTrue(Schema::hasColumn('user_course_role', 'church_id'));
        $this->assertTrue(Schema::hasColumn('church', 'permissions_version'));
    }

    public function test_roles_and_grants_can_be_stamped_with_a_church(): void
    {
        $main = Church::main();
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $user = $this->createUser(['email' => 'rbac-church@example.com']);
        $this->assignCourseRole($user, $course, $role);

        // Stamp a role + grant with the church (the T3-expand backfill does this for real data).
        DB::table('roles')->where('role_id', $role->role_id)->update(['church_id' => $main->church_id]);
        DB::table('user_course_role')
            ->where('user_id', $user->user_id)->where('course_id', $course->course_id)
            ->update(['church_id' => $main->church_id]);

        $this->assertEquals($main->church_id, Role::find($role->role_id)->church_id);
        $this->assertEquals(
            $main->church_id,
            UserCourseRole::where('user_id', $user->user_id)->where('course_id', $course->course_id)->value('church_id')
        );
    }

    public function test_permissions_version_defaults_and_bumps(): void
    {
        $main = Church::main();
        $this->assertSame(1, (int) $main->permissions_version);

        $main->bumpPermissionsVersion();

        $this->assertSame(2, (int) $main->fresh()->permissions_version);
    }
}
