<?php

namespace Tests\Feature\Structure;

use App\Exceptions\OptimisticLockException;
use App\Models\Attendance;
use App\Models\Session;
use App\Services\AttendanceCloseService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class AttendanceOptimisticLockTest extends EventModuleTestCase
{
    public function test_attendance_has_lock_version_column(): void
    {
        $this->assertTrue(Schema::hasColumn('attendance', 'lock_version'));
    }

    public function test_create_or_update_increments_lock_version(): void
    {
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $student = $this->createUser();
        $staff = $this->createUser(['is_superadmin' => true]);
        $this->assignCourseRole($student, $course, $role);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_date' => now()->toDateString(),
            'session_title' => 'Lock session',
        ]);

        $service = app(AttendanceCloseService::class);
        $first = $service->createOrUpdateRecord($session, $student->user_id, 'Present', $staff->user_id);
        $this->assertSame(0, (int) $first->lock_version);

        $second = $service->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Late',
            $staff->user_id,
            expectedLockVersion: 0
        );
        $this->assertSame(1, (int) $second->lock_version);
    }

    public function test_stale_lock_version_throws_conflict(): void
    {
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $student = $this->createUser();
        $staff = $this->createUser(['is_superadmin' => true]);
        $this->assignCourseRole($student, $course, $role);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_date' => now()->toDateString(),
            'session_title' => 'Conflict session',
        ]);

        $service = app(AttendanceCloseService::class);
        $service->createOrUpdateRecord($session, $student->user_id, 'Present', $staff->user_id);
        $service->createOrUpdateRecord($session, $student->user_id, 'Late', $staff->user_id, expectedLockVersion: 0);

        $this->expectException(OptimisticLockException::class);
        $service->createOrUpdateRecord($session, $student->user_id, 'Absent', $staff->user_id, expectedLockVersion: 0);
    }

    public function test_update_status_endpoint_returns_409_on_stale_lock(): void
    {
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $student = $this->createUser();
        $staff = $this->createUser(['is_superadmin' => true]);
        $this->assignCourseRole($student, $course, $role);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_date' => now()->toDateString(),
            'session_title' => 'API lock session',
        ]);

        $attendance = Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $staff->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
            'lock_version' => 2,
        ]);

        $response = $this->actingAs($staff)->postJson('/attendance/'.$attendance->attendance_id.'/status', [
            'status' => 'Absent',
            'lock_version' => 1,
        ]);

        $response->assertStatus(409);
        $this->assertSame(2, (int) $attendance->fresh()->lock_version);
    }
}
