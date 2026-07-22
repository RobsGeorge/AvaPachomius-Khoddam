<?php

namespace Tests\Feature\Structure;

use App\Models\Enrollment;
use App\Models\ServiceUnit;
use App\Models\UserCourseRole;
use App\Services\CourseRoleAssignmentService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class EnrollmentDualWriteTest extends EventModuleTestCase
{
    public function test_enrollments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('enrollments'));
        $this->assertTrue(Schema::hasColumn('enrollments', 'user_course_role_id'));
        $this->assertTrue(Schema::hasColumn('enrollments', 'service_unit_id'));
    }

    public function test_user_course_role_dual_writes_enrollment(): void
    {
        $course = $this->createCourse(['title' => 'Enrollment Dual']);
        $role = $this->createRole('student');
        $user = $this->createUser();
        $this->ensureServiceMembership($user, $course);

        $assignment = app(CourseRoleAssignmentService::class)->assign($user, $course->course_id, $role->role_id, notify: false);

        $enrollment = Enrollment::query()
            ->where('user_course_role_id', $assignment->user_course_role_id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame((int) $user->user_id, (int) $enrollment->user_id);
        $this->assertSame((int) $course->course_id, (int) $enrollment->course_id);
        $this->assertSame((int) $role->role_id, (int) $enrollment->role_id);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->status);

        $unit = ServiceUnit::query()->where('course_id', $course->course_id)->first();
        if ($unit) {
            $this->assertSame((int) $unit->service_unit_id, (int) $enrollment->service_unit_id);
        }
    }

    public function test_deleting_user_course_role_removes_enrollment(): void
    {
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $user = $this->createUser();

        $assignment = UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $course->course_id,
            'role_id' => $role->role_id,
            'church_id' => $course->church_id,
        ]);

        $this->assertTrue(Enrollment::query()->where('user_course_role_id', $assignment->user_course_role_id)->exists());

        $assignment->delete();

        $this->assertFalse(Enrollment::query()->where('user_course_role_id', $assignment->user_course_role_id)->exists());
    }
}
