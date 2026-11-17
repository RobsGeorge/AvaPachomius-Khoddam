<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Church;
use App\Models\Lecture;
use App\Models\Module;
use App\Models\Person;
use App\Models\Session;
use App\Services\AttendanceCloseService;
use App\Services\AttendanceGrain;
use App\Tenancy\TenantContext;
use Tests\Support\EventModuleTestCase;

/**
 * Fill-missing Absents must not permanently block QR check-in.
 * QR may upgrade Absent → Present; only attended statuses stay locked.
 */
class QrCheckinUpgradesAbsentTest extends EventModuleTestCase
{
    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::main() ?? $this->createChurch(['slug' => 'qr-absent-main']);
        TenantContext::set($this->church);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_qr_user_path_upgrades_fill_missing_absent_to_present(): void
    {
        $roles = $this->seedBasicRoles();
        $staff = $this->createUser([
            'is_superadmin' => true,
            'email' => 'qr-absent-staff@example.com',
        ]);
        $student = $this->createUser([
            'first_name' => 'Absent',
            'second_name' => 'Upgrade',
            'email' => 'qr-absent-student@example.com',
        ]);
        $course = $this->createCourse(['title' => 'QR Absent Course']);
        $this->assignCourseRole($staff, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Today Meeting',
            'session_date' => now()->toDateString(),
        ]);

        $filled = app(AttendanceCloseService::class)->fillMissingRecords(
            $session,
            (int) $staff->user_id,
            'Absent',
        );
        $this->assertSame(1, $filled);
        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $student->user_id,
            'status' => 'Absent',
        ]);

        $this->actingAs($staff)
            ->from(route('attendance.sessions', ['user_id' => $student->user_id]))
            ->post(route('attendance.record', $session), [
                'student_user_id' => $student->user_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::where('session_id', $session->session_id)->count());
        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'user_id' => $student->user_id,
            'status' => 'Present',
        ]);

        $this->actingAs($staff)
            ->from(route('attendance.sessions', ['user_id' => $student->user_id]))
            ->post(route('attendance.record', $session), [
                'student_user_id' => $student->user_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_qr_person_path_upgrades_absent_to_present(): void
    {
        $roles = $this->seedBasicRoles();
        $staff = $this->createUser([
            'is_superadmin' => true,
            'email' => 'qr-person-staff@example.com',
        ]);
        $student = $this->createUser([
            'first_name' => 'Person',
            'second_name' => 'Path',
            'email' => 'qr-person-student@example.com',
        ]);
        $course = $this->createCourse(['title' => 'QR Person Course']);
        $this->assignCourseRole($staff, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $person = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'Person',
            'second_name' => 'Path',
            'third_name' => 'A',
            'date_of_birth' => '2000-01-01',
            'is_minor' => false,
        ]);
        $student->forceFill(['person_id' => $person->person_id])->save();

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Person Today',
            'session_date' => now()->toDateString(),
        ]);

        Attendance::create([
            'user_id' => $student->user_id,
            'person_id' => $person->person_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $staff->user_id,
            'status' => 'Absent',
            'attendance_time' => now(),
        ]);

        $this->actingAs($staff)
            ->from(route('attendance.all'))
            ->post(route('attendance.record', $session), [
                'student_person_id' => $person->person_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::where('session_id', $session->session_id)->count());
        $this->assertDatabaseHas('attendance', [
            'session_id' => $session->session_id,
            'person_id' => $person->person_id,
            'status' => 'Present',
        ]);
    }

    public function test_lecture_grain_qr_upgrades_absent_for_that_lecture_only(): void
    {
        $roles = $this->seedBasicRoles();
        $staff = $this->createUser([
            'is_superadmin' => true,
            'email' => 'qr-lecture-staff@example.com',
        ]);
        $student = $this->createUser([
            'email' => 'qr-lecture-student@example.com',
        ]);
        $course = $this->createCourse([
            'title' => 'Lecture QR Course',
            'attendance_grain' => AttendanceGrain::LECTURE,
        ]);
        $this->assignCourseRole($staff, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $module = Module::create(['title' => 'M', 'description' => 'M']);
        $course->modules()->attach($module->module_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Lecture Meeting',
            'session_date' => now()->toDateString(),
            'attendance_grain' => AttendanceGrain::LECTURE,
        ]);
        $lectureA = Lecture::create([
            'module_id' => $module->module_id,
            'session_id' => $session->session_id,
            'title' => 'A',
            'week_number' => 1,
            'order_index' => 1,
        ]);
        $lectureB = Lecture::create([
            'module_id' => $module->module_id,
            'session_id' => $session->session_id,
            'title' => 'B',
            'week_number' => 1,
            'order_index' => 2,
        ]);

        app(AttendanceCloseService::class)->fillMissingRecords(
            $session,
            (int) $staff->user_id,
            'Absent',
        );

        $this->actingAs($staff)
            ->post(route('attendance.record', $session), [
                'student_user_id' => $student->user_id,
                'lecture_id' => $lectureA->lecture_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'lecture_id' => $lectureA->lecture_id,
            'user_id' => $student->user_id,
            'status' => 'Present',
        ]);
        $this->assertDatabaseHas('attendance', [
            'lecture_id' => $lectureB->lecture_id,
            'user_id' => $student->user_id,
            'status' => 'Absent',
        ]);
    }

    public function test_fill_missing_refuses_future_sessions(): void
    {
        $roles = $this->seedBasicRoles();
        $staff = $this->createUser([
            'is_superadmin' => true,
            'email' => 'fill-future-staff@example.com',
        ]);
        $student = $this->createUser(['email' => 'fill-future-student@example.com']);
        $course = $this->createCourse(['title' => 'Future Fill Course']);
        $this->assignCourseRole($staff, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Next Week',
            'session_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAs($staff)
            ->from(route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
            ]))
            ->post(route('sessions.attendance.fill-missing', $session), [
                'status' => 'Absent',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Attendance::where('session_id', $session->session_id)->count());
    }
}
