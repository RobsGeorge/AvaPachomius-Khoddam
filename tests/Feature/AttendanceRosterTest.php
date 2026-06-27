<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Session;
use App\Models\User;
use App\Services\AttendanceCloseService;
use Tests\Support\EventModuleTestCase;

class AttendanceRosterTest extends EventModuleTestCase
{
    /** @return array{admin: User, course: Course, session: Session, students: array<int, User>} */
    private function seedSessionWithStudents(int $studentCount = 2): array
    {
        $admin = $this->createUser(['email' => 'roster-admin@example.com']);
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('Student');
        $course = $this->createCourse(['title' => 'Roster Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Week 1',
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $students = [];
        for ($i = 0; $i < $studentCount; $i++) {
            $student = $this->createUser([
                'first_name' => 'Roster',
                'second_name' => 'Student',
                'third_name' => (string) ($i + 1),
                'email' => "roster-student-{$i}@example.com",
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        return compact('admin', 'course', 'session', 'students');
    }

    public function test_fill_missing_creates_records_for_enrolled_students_without_attendance(): void
    {
        ['admin' => $admin, 'session' => $session, 'students' => $students] = $this->seedSessionWithStudents(2);

        Attendance::create([
            'user_id' => $students[0]->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('sessions.attendance.fill-missing', $session))
            ->assertRedirect(route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
            ]));

        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $students[1]->user_id,
            'status' => 'Absent',
            'taken_by_id' => $admin->user_id,
        ]);

        $this->assertDatabaseCount('attendance', 2);
    }

    public function test_manual_attendance_can_be_added_to_closed_session(): void
    {
        ['admin' => $admin, 'session' => $session, 'students' => $students] = $this->seedSessionWithStudents(1);

        $session->update([
            'attendance_closed_at' => now(),
            'attendance_closed_by_id' => $admin->user_id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('sessions.attendance.store', $session), [
                'user_id' => $students[0]->user_id,
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $students[0]->user_id,
            'status' => 'Present',
            'taken_by_id' => $admin->user_id,
        ]);
    }

    public function test_single_session_report_shows_enrolled_students_without_records(): void
    {
        ['admin' => $admin, 'session' => $session, 'students' => $students] = $this->seedSessionWithStudents(2);

        $this->actingAs($admin)
            ->get(route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
            ]))
            ->assertOk()
            ->assertSee(__('pages.not_recorded'))
            ->assertSee(__('pages.roster_missing'))
            ->assertSee('Roster Student 1')
            ->assertSee('Roster Student 2');
    }

    public function test_session_roster_service_reports_missing_students(): void
    {
        ['admin' => $admin, 'session' => $session, 'students' => $students] = $this->seedSessionWithStudents(2);

        Attendance::create([
            'user_id' => $students[0]->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
        ]);

        $roster = app(AttendanceCloseService::class)->sessionRoster($session);

        $this->assertSame(2, $roster['enrolled']);
        $this->assertSame(1, $roster['recorded']);
        $this->assertSame(1, $roster['missing']);
        $this->assertTrue($roster['rows'][1]['missing']);
    }

    public function test_session_without_attendance_appears_in_session_filter_options(): void
    {
        ['admin' => $admin, 'session' => $session] = $this->seedSessionWithStudents(1);

        $this->actingAs($admin)
            ->get(route('attendance.all'))
            ->assertOk()
            ->assertSee($session->session_title);
    }
}
