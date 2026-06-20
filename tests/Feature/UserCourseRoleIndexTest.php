<?php

namespace Tests\Feature;

use App\Models\UserCourseRole;
use Tests\Support\EventModuleTestCase;

class UserCourseRoleIndexTest extends EventModuleTestCase
{
    public function test_admin_can_view_user_course_roles_index(): void
    {
        $admin = $this->createUser([
            'email' => 'roles-admin@example.com',
            'registration_completed' => true,
        ]);
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('Student');
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $adminRole);

        $student = $this->createUser([
            'email' => 'roles-student@example.com',
            'registration_completed' => false,
        ]);
        UserCourseRole::create([
            'user_id' => $student->user_id,
            'course_id' => $course->course_id,
            'role_id' => $studentRole->role_id,
        ]);

        $this->actingAs($admin)
            ->get(route('user-course-roles.index'))
            ->assertOk()
            ->assertSee(__('pages.account_status'))
            ->assertSee(__('pages.account_status_incomplete'));
    }
}
