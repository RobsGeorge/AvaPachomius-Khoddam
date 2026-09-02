<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\Module;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Services\AttendanceCloseService;
use Illuminate\Support\Facades\DB;
use Tests\Support\EventModuleTestCase;

/**
 * Course admins (and staff with attendance.edit) may correct any student's
 * status after session close or module end. Course closed/archived still
 * strips write keys. The roster UI posts to /attendance/{id}/status.
 */
class CourseAdminClosedAttendanceTest extends EventModuleTestCase
{
    /** @return array{admin: \App\Models\User, course: Course, session: Session, student: \App\Models\User, attendance: Attendance} */
    private function seedEndedModuleWithClosedSession(): array
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'closed-att-admin@example.com']);
        $student = $this->createUser([
            'first_name' => 'Closed',
            'second_name' => 'Student',
            'email' => 'closed-att-student@example.com',
        ]);
        $course = $this->createCourse(['title' => 'Closed Attendance Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $module = Module::create(['title' => 'Ended Week', 'description' => 'Done']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
            'ended_at' => now(),
        ]);

        $session = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Ended Week Session',
            'session_date' => now()->subDay()->toDateString(),
            'session_start_time' => '09:00:00',
        ]);

        if (DB::getSchemaBuilder()->hasTable('module_session')) {
            DB::table('module_session')->insert([
                'module_id' => $module->module_id,
                'session_id' => $session->session_id,
                'week_number' => 1,
            ]);
        }

        $attendance = Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Absent',
            'attendance_time' => now()->subDay(),
        ]);

        app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);
        $session->refresh();

        return compact('admin', 'course', 'session', 'student', 'attendance');
    }

    public function test_course_admin_can_change_status_after_module_ended_and_session_closed(): void
    {
        [
            'admin' => $admin,
            'attendance' => $attendance,
        ] = $this->seedEndedModuleWithClosedSession();

        $this->assertNotNull($attendance->session?->fresh()->attendance_closed_at);

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status-post', $attendance->attendance_id), [
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Present', $attendance->fresh()->status);
    }

    public function test_course_admin_can_change_status_via_legacy_update_status_path(): void
    {
        [
            'admin' => $admin,
            'attendance' => $attendance,
        ] = $this->seedEndedModuleWithClosedSession();

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status', $attendance->attendance_id), [
                'status' => 'Late',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Late', $attendance->fresh()->status);
    }

    public function test_staff_with_attendance_edit_only_can_correct_after_close(): void
    {
        [
            'course' => $course,
            'attendance' => $attendance,
        ] = $this->seedEndedModuleWithClosedSession();

        $editor = $this->createUser(['email' => 'closed-att-editor@example.com']);
        $role = $this->courseRoleWithPermissions($course, 'attendance-editor', [
            'attendance.edit',
            'attendance.view_all',
        ]);
        $this->assignCourseRole($editor, $course, $role);

        $this->actingAs($editor)
            ->postJson(route('attendance.update-status-post', $attendance->attendance_id), [
                'status' => 'Permission',
                'permission_reason' => 'Family funeral',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $fresh = $attendance->fresh();
        $this->assertSame('Permission', $fresh->status);
        $this->assertSame('Family funeral', $fresh->permission_reason);
    }

    public function test_student_cannot_change_attendance_after_module_ended(): void
    {
        [
            'student' => $student,
            'attendance' => $attendance,
        ] = $this->seedEndedModuleWithClosedSession();

        $this->actingAs($student)
            ->postJson(route('attendance.update-status-post', $attendance->attendance_id), [
                'status' => 'Present',
            ])
            ->assertForbidden();

        $this->assertSame('Absent', $attendance->fresh()->status);
    }

    public function test_course_admin_cannot_change_attendance_when_course_is_closed(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'course-closed-admin@example.com']);
        $student = $this->createUser(['email' => 'course-closed-student@example.com']);
        $course = $this->createCourse([
            'title' => 'Fully Closed Course',
            'status' => Course::STATUS_CLOSED,
        ]);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Archived session',
            'session_date' => now()->subWeek()->toDateString(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Absent',
            'attendance_time' => now()->subWeek(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('attendance.update-status-post', $attendance->attendance_id), [
                'status' => 'Present',
            ])
            ->assertForbidden();

        $this->assertSame('Absent', $attendance->fresh()->status);
    }

    public function test_ui_status_path_resyncs_grade_after_close(): void
    {
        AttendancePolicy::current()->update([
            'is_enabled' => true,
            'late_threshold_minutes' => 15,
            'late_grade_percentage' => 50,
        ]);

        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
            'attendance' => $attendance,
        ] = $this->seedEndedModuleWithClosedSession();

        GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'attendance',
            'name' => 'Attendance',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);

        app(AttendanceCloseService::class)->closeSession($session->fresh(), $admin->user_id);

        $this->actingAs($admin)
            ->postJson('/attendance/'.$attendance->attendance_id.'/status', [
                'status' => 'Late',
                'lock_version' => (int) $attendance->fresh()->lock_version,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $grade = StudentGrade::query()
            ->where('user_id', $student->user_id)
            ->whereHas('item', fn ($q) => $q->where('session_id', $session->session_id))
            ->first();

        $this->assertNotNull($grade);
        $this->assertEquals(50.0, (float) $grade->score);
        $this->assertSame('Late', $grade->notes);
    }
}
