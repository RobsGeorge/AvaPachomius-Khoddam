<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\Module;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\AttendanceCloseService;
use App\Services\AttendanceGrain;
use App\Services\GraduationService;
use Carbon\Carbon;
use Tests\Support\EventModuleTestCase;

class LectureAttendanceModeTest extends EventModuleTestCase
{
    private function attendanceInstant(string $localDateTime): Carbon
    {
        return Carbon::parse($localDateTime, config('attendance.timezone'))->utc();
    }

    /**
     * @return array{admin: User, course: Course, session: Session, student: User, lectureA: Lecture, lectureB: Lecture}
     */
    private function seedMeeting(string $grain = AttendanceGrain::LECTURE): array
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'grain-admin-'.uniqid().'@example.com']);
        $student = $this->createUser(['email' => 'grain-student-'.uniqid().'@example.com']);
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse([
            'title' => 'Grain Course',
            'attendance_grain' => $grain,
        ]);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $module = Module::create(['title' => 'Module', 'description' => 'M']);
        $course->modules()->attach($module->module_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Meeting',
            'session_date' => '2026-04-02',
            'session_start_time' => '09:00:00',
            'attendance_grain' => $grain,
            'week_number' => 1,
        ]);

        $lectureA = Lecture::create([
            'module_id' => $module->module_id,
            'session_id' => $session->session_id,
            'title' => 'Lecture A',
            'week_number' => 1,
            'order_index' => 1,
        ]);
        $lectureB = Lecture::create([
            'module_id' => $module->module_id,
            'session_id' => $session->session_id,
            'title' => 'Lecture B',
            'week_number' => 1,
            'order_index' => 2,
        ]);

        return compact('admin', 'course', 'session', 'student', 'lectureA', 'lectureB');
    }

    public function test_new_session_copies_the_course_default_roll_call(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'grain-copy@example.com']);
        $course = $this->createCourse([
            'title' => 'Copy Course',
            'attendance_grain' => AttendanceGrain::LECTURE,
        ]);
        $module = Module::create(['title' => 'Copy Module', 'description' => 'M']);
        $course->modules()->attach($module->module_id);

        $this->actingAs($admin)
            ->get(route('sessions.create'))
            ->assertOk()
            ->assertSee(__('pages.attendance_grain'), false)
            ->assertSee(__('pages.attendance_grain_lecture'), false);

        $this->actingAs($admin)
            ->post(route('sessions.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'session_title' => 'Copied',
                'creation_mode' => 'single',
                'single_date' => '2026-05-01',
                'session_start_time' => '09:00',
            ])
            ->assertRedirect(route('sessions.index'));

        $this->assertDatabaseHas('session', [
            'course_id' => $course->course_id,
            'session_title' => 'Copied',
            'attendance_grain' => AttendanceGrain::LECTURE,
        ]);
    }

    public function test_session_grain_keeps_one_roll_call_when_two_lectures_are_linked(): void
    {
        ['admin' => $admin, 'session' => $session, 'student' => $student] = $this->seedMeeting(AttendanceGrain::SESSION);
        $service = app(AttendanceCloseService::class);

        $service->createOrUpdateRecord($session, $student->user_id, 'Present', $admin->user_id);
        $service->createOrUpdateRecord($session, $student->user_id, 'Absent', $admin->user_id);

        $this->assertSame(1, Attendance::where('session_id', $session->session_id)->count());
        $this->assertSame('Absent', Attendance::where('session_id', $session->session_id)->value('status'));
        $this->assertNull(Attendance::where('session_id', $session->session_id)->value('lecture_id'));
    }

    public function test_lecture_grain_stores_an_independent_status_per_lecture(): void
    {
        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
            'lectureA' => $lectureA,
            'lectureB' => $lectureB,
        ] = $this->seedMeeting();

        $service = app(AttendanceCloseService::class);
        $service->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Present',
            $admin->user_id,
            lectureId: $lectureA->lecture_id,
        );
        $service->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Absent',
            $admin->user_id,
            lectureId: $lectureB->lecture_id,
        );

        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'lecture_id' => $lectureA->lecture_id,
            'user_id' => $student->user_id,
            'status' => 'Present',
        ]);
        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'lecture_id' => $lectureB->lecture_id,
            'user_id' => $student->user_id,
            'status' => 'Absent',
        ]);
        $this->assertSame(2, Attendance::where('session_id', $session->session_id)->count());

        $this->actingAs($admin)
            ->get(route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
                'lecture_id' => $lectureA->lecture_id,
            ]))
            ->assertOk()
            ->assertSee('Lecture A', false)
            ->assertSee('Lecture B', false);
    }

    public function test_roll_call_locks_after_the_first_mark(): void
    {
        ['admin' => $admin, 'course' => $course, 'session' => $session, 'student' => $student] = $this->seedMeeting();
        $moduleId = $session->module_id;

        app(AttendanceCloseService::class)->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Present',
            $admin->user_id,
            lectureId: $session->lectures()->value('lecture_id'),
        );

        $this->actingAs($admin)
            ->from(route('sessions.edit', $session->session_id))
            ->put(route('sessions.update', $session->session_id), [
                'course_id' => $course->course_id,
                'module_id' => $moduleId,
                'session_title' => 'Meeting',
                'session_date' => '2026-04-02',
                'session_start_time' => '09:00',
                'attendance_grain' => AttendanceGrain::SESSION,
            ])
            ->assertSessionHasErrors('attendance_grain');

        $this->assertSame(AttendanceGrain::LECTURE, $session->fresh()->attendance_grain);

        $this->actingAs($admin)
            ->get(route('sessions.edit', $session->session_id))
            ->assertOk()
            ->assertSee(__('pages.attendance_grain_locked'), false);
    }

    public function test_close_marks_each_lecture_roster_absent_and_scores_occasions(): void
    {
        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
            'lectureA' => $lectureA,
            'lectureB' => $lectureB,
        ] = $this->seedMeeting();

        GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'attendance',
            'name' => 'Attendance',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);

        $present = app(AttendanceCloseService::class)->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Present',
            $admin->user_id,
            lectureId: $lectureA->lecture_id,
        );
        $present->update([
            'attendance_time' => $this->attendanceInstant('2026-04-02 09:05:00'),
        ]);

        app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $this->assertDatabaseHas('attendance', [
            'lecture_id' => $lectureB->lecture_id,
            'user_id' => $student->user_id,
            'status' => 'Absent',
        ]);

        $this->assertSame(2, GradeItem::where('session_id', $session->session_id)->count());

        $pct = app(GraduationService::class)
            ->attendancePercentagesForCourse($course)
            ->get($student->user_id);

        $this->assertEquals(50.0, $pct);
    }

    public function test_on_time_arrival_keeps_a_later_lecture_mark_present(): void
    {
        AttendancePolicy::current()->update([
            'is_enabled' => true,
            'late_threshold_minutes' => 15,
            'late_grade_percentage' => 50,
        ]);

        [
            'admin' => $admin,
            'session' => $session,
            'student' => $student,
            'lectureA' => $lectureA,
            'lectureB' => $lectureB,
        ] = $this->seedMeeting();

        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'lecture_id' => $lectureA->lecture_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => $this->attendanceInstant('2026-04-02 09:05:00'),
        ]);
        Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'lecture_id' => $lectureB->lecture_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => $this->attendanceInstant('2026-04-02 10:30:00'),
        ]);

        $result = app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $this->assertSame(0, $result['late_marked']);
        $this->assertSame('Present', Attendance::where('lecture_id', $lectureA->lecture_id)->value('status'));
        $this->assertSame('Present', Attendance::where('lecture_id', $lectureB->lecture_id)->value('status'));
    }

    public function test_late_arrival_marks_every_present_lecture_late_at_half_credit(): void
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
            'lectureA' => $lectureA,
            'lectureB' => $lectureB,
        ] = $this->seedMeeting();

        GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'attendance',
            'name' => 'Attendance',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);

        foreach ([$lectureA, $lectureB] as $lecture) {
            Attendance::create([
                'user_id' => $student->user_id,
                'session_id' => $session->session_id,
                'lecture_id' => $lecture->lecture_id,
                'taken_by_id' => $admin->user_id,
                'status' => 'Present',
                'attendance_time' => $this->attendanceInstant('2026-04-02 09:25:00'),
            ]);
        }

        $result = app(AttendanceCloseService::class)->closeSession($session, $admin->user_id);

        $this->assertSame(2, $result['late_marked']);
        $this->assertSame(2, Attendance::where('session_id', $session->session_id)->where('status', 'Late')->count());

        $scores = StudentGrade::query()
            ->where('user_id', $student->user_id)
            ->pluck('score')
            ->map(fn ($score) => (float) $score)
            ->all();

        sort($scores);
        $this->assertEquals([50.0, 50.0], $scores);
    }

    public function test_lecture_with_attendance_cannot_be_deleted(): void
    {
        [
            'admin' => $admin,
            'course' => $course,
            'session' => $session,
            'student' => $student,
            'lectureA' => $lectureA,
        ] = $this->seedMeeting();

        app(AttendanceCloseService::class)->createOrUpdateRecord(
            $session,
            $student->user_id,
            'Present',
            $admin->user_id,
            lectureId: $lectureA->lecture_id,
        );

        $this->actingAs($admin)
            ->delete(route('lectures.destroy', $lectureA->lecture_id), [
                'course_id' => $course->course_id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lectures', ['lecture_id' => $lectureA->lecture_id]);
    }
}
