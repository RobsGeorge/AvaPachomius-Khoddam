<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\GradeCategory;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Services\AttendanceCloseService;
use Tests\Support\EventModuleTestCase;

/**
 * Post-close attendance status edits must re-sync attendance gradebook scores.
 */
class AttendanceGradeResyncTest extends EventModuleTestCase
{
    /** @return array{admin: \App\Models\User, course: \App\Models\Course, session: Session, student: \App\Models\User, category: GradeCategory} */
    private function seedCloseableSession(): array
    {
        AttendancePolicy::current()->update([
            'is_enabled' => true,
            'late_threshold_minutes' => 15,
            'late_grade_percentage' => 50,
        ]);

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'resync-admin@example.com']);
        $student = $this->createUser([
            'first_name' => 'Resync',
            'second_name' => 'Student',
            'email' => 'resync-student@example.com',
        ]);
        $course = $this->createCourse(['title' => 'Resync Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $category = GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'attendance',
            'name' => 'Attendance',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Resync Week',
            'session_date' => '2026-03-10',
            'session_start_time' => '09:00:00',
        ]);

        return compact('admin', 'course', 'session', 'student', 'category');
    }

    private function gradeFor(Session $session, int $userId): ?StudentGrade
    {
        return StudentGrade::query()
            ->where('user_id', $userId)
            ->whereHas('item', fn ($q) => $q->where('session_id', $session->session_id))
            ->first();
    }

    public function test_absent_to_late_after_close_resyncs_half_credit_grade(): void
    {
        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $attendance = Attendance::query()
            ->where('session_id', $session->session_id)
            ->where('user_id', $student->user_id)
            ->firstOrFail();

        $this->assertSame('Absent', $attendance->status);
        $this->assertEquals(0.0, (float) $this->gradeFor($session, $student->user_id)->score);

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status', $attendance->attendance_id), [
                'status' => 'Late',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $grade = $this->gradeFor($session, $student->user_id);
        $this->assertNotNull($grade);
        $this->assertEquals(50.0, (float) $grade->score);
        $this->assertSame('Late', $grade->notes);
        $this->assertDatabaseHas('attendance', [
            'attendance_id' => $attendance->attendance_id,
            'status' => 'Late',
        ]);
    }

    public function test_late_to_absent_after_close_zeros_grade_and_clears_notes(): void
    {
        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Late',
            'attendance_time' => now(),
        ]);

        app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $attendance = Attendance::query()
            ->where('session_id', $session->session_id)
            ->where('user_id', $student->user_id)
            ->firstOrFail();

        $this->assertEquals(50.0, (float) $this->gradeFor($session, $student->user_id)->score);

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status', $attendance->attendance_id), [
                'status' => 'Absent',
            ])
            ->assertOk();

        $grade = $this->gradeFor($session, $student->user_id);
        $this->assertEquals(0.0, (float) $grade->score);
        $this->assertNull($grade->notes);
    }

    public function test_status_change_while_session_open_does_not_create_grades(): void
    {
        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        $attendance = Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Absent',
            'attendance_time' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status', $attendance->attendance_id), [
                'status' => 'Late',
            ])
            ->assertOk();

        $this->assertNull($this->gradeFor($session, $student->user_id));
        $this->assertDatabaseHas('attendance', [
            'attendance_id' => $attendance->attendance_id,
            'status' => 'Late',
        ]);
    }

    public function test_manual_add_after_close_creates_matching_grade(): void
    {
        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        // Close with no enrolled student recorded → absent filled for student, then
        // add a second student via store after close.
        app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $roles = $this->seedBasicRoles();
        $lateJoiner = $this->createUser([
            'first_name' => 'Late',
            'second_name' => 'Joiner',
            'email' => 'late-joiner@example.com',
        ]);
        $this->assignCourseRole($lateJoiner, $session->course, $roles['student']);

        $this->actingAs($admin)
            ->postJson(route('sessions.attendance.store', $session), [
                'user_id' => $lateJoiner->user_id,
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $grade = $this->gradeFor($session, $lateJoiner->user_id);
        $this->assertNotNull($grade);
        $this->assertEquals(100.0, (float) $grade->score);
        $this->assertNull($grade->notes);
    }
}
