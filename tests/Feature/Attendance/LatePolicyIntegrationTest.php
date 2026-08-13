<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\GradeCategory;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Services\AttendanceCloseService;
use App\Services\GraduationService;
use Carbon\Carbon;
use Tests\Support\EventModuleTestCase;

/**
 * End-to-end: late policy (50%) on session close → gradebook + graduation % + attendance report.
 */
class LatePolicyIntegrationTest extends EventModuleTestCase
{
    /**
     * Wall-clock time in the attendance timezone, stored as a UTC instant
     * (app timezone is UTC; AttendanceLatePolicyService compares in Africa/Cairo).
     */
    private function attendanceInstant(string $localDateTime): Carbon
    {
        return Carbon::parse($localDateTime, config('attendance.timezone'))->utc();
    }

    /** @return array{admin: \App\Models\User, course: \App\Models\Course, session: Session, student: \App\Models\User, category: GradeCategory} */
    private function seedCloseableSession(): array
    {
        AttendancePolicy::current()->update([
            'is_enabled' => true,
            'late_threshold_minutes' => 15,
            'late_grade_percentage' => 50,
        ]);

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'late-admin@example.com']);
        $student = $this->createUser([
            'first_name' => 'Late',
            'second_name' => 'Fifty',
            'email' => 'late-fifty@example.com',
        ]);
        $course = $this->createCourse(['title' => 'Late Policy Course']);
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
            'session_title' => 'Late Week',
            'session_date' => '2026-03-10',
            'session_start_time' => '09:00:00',
        ]);

        return compact('admin', 'course', 'session', 'student', 'category');
    }

    public function test_closing_session_marks_late_and_syncs_half_credit_grade(): void
    {
        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            // 20 minutes after 09:00 start → past 15-min grace → Late
            'attendance_time' => $this->attendanceInstant('2026-03-10 09:20:00'),
        ]);

        $result = app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $this->assertSame(1, $result['late_marked']);
        $this->assertGreaterThan(0, $result['grades_synced']);

        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $student->user_id,
            'status' => 'Late',
        ]);

        $grade = StudentGrade::query()
            ->where('user_id', $student->user_id)
            ->whereHas('item', fn ($q) => $q->where('session_id', $session->session_id))
            ->first();

        $this->assertNotNull($grade);
        $this->assertEquals(50.0, (float) $grade->score);
        $this->assertSame('Late', $grade->notes);

        $course->load(['gradeCategories.items.grades']);
        $this->assertEquals(50.0, $course->studentTotalGrade($student->user_id));
    }

    public function test_graduation_attendance_percentage_gives_late_half_credit(): void
    {
        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        // Session A: Present
        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => $this->attendanceInstant('2026-03-10 09:05:00'),
        ]);

        // Session B: Late
        $lateSession = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Late Week 2',
            'session_date' => '2026-03-17',
            'session_start_time' => '09:00:00',
        ]);
        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $lateSession->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Late',
            'attendance_time' => $this->attendanceInstant('2026-03-17 09:25:00'),
        ]);

        // (1 Present + 0.5 Late) / 2 = 75%
        $pct = app(GraduationService::class)
            ->attendancePercentagesForCourse($course)
            ->get($student->user_id);

        $this->assertEquals(75.0, $pct);
    }

    public function test_attendance_report_rate_applies_fifty_percent_late_credit(): void
    {
        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
        ] = $this->seedCloseableSession();

        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => $this->attendanceInstant('2026-03-10 09:05:00'),
        ]);

        $lateSession = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Late Week 2',
            'session_date' => '2026-03-17',
            'session_start_time' => '09:00:00',
        ]);
        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $lateSession->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Late',
            'attendance_time' => $this->attendanceInstant('2026-03-17 09:25:00'),
        ]);

        // Before fix this was 50% (Late counted as 0). With policy: (1+0.5)/2 = 75%.
        $this->actingAs($admin)
            ->get(route('attendance.report'))
            ->assertOk()
            ->assertSee('Late Fifty')
            ->assertSee('75.0%');
    }

    public function test_on_time_present_is_not_converted_to_late(): void
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
            'status' => 'Present',
            // Well inside the 15-minute grace window
            'attendance_time' => $this->attendanceInstant('2026-03-10 09:10:00'),
        ]);

        $result = app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $this->assertSame(0, $result['late_marked']);
        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $student->user_id,
            'status' => 'Present',
        ]);

        $grade = StudentGrade::query()
            ->where('user_id', $student->user_id)
            ->whereHas('item', fn ($q) => $q->where('session_id', $session->session_id))
            ->first();

        $this->assertEquals(100.0, (float) $grade->score);
    }
}
