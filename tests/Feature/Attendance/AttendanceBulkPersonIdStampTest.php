<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Session;
use App\Services\AttendanceCloseService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * Bulk fill/close must stamp person_id; person-path upserts must heal legacy
 * user-only rows instead of inserting a second attendance record.
 */
class AttendanceBulkPersonIdStampTest extends EventModuleTestCase
{
    public function test_fill_missing_stamps_person_id_and_person_path_does_not_duplicate(): void
    {
        $this->assertTrue(Schema::hasColumn('attendance', 'person_id'));

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'bulk-person-admin@example.com']);
        $student = $this->createUser(['email' => 'bulk-person-student@example.com']);
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse(['title' => 'Bulk Person Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assertNotNull($student->person_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Bulk Person Meeting',
            'session_date' => now()->toDateString(),
        ]);

        $service = app(AttendanceCloseService::class);
        $filled = $service->fillMissingRecords($session, (int) $admin->user_id, 'Absent');
        $this->assertSame(1, $filled);

        $absent = Attendance::query()->where('session_id', $session->session_id)->first();
        $this->assertNotNull($absent);
        $this->assertSame('Absent', $absent->status);
        $this->assertSame((int) $student->user_id, (int) $absent->user_id);
        $this->assertSame((int) $student->person_id, (int) $absent->person_id);

        $updated = $service->createOrUpdateForPerson(
            $session,
            (int) $student->person_id,
            'Present',
            (int) $admin->user_id,
        );

        $this->assertSame((int) $absent->attendance_id, (int) $updated->attendance_id);
        $this->assertSame('Present', $updated->status);
        $this->assertSame((int) $student->person_id, (int) $updated->person_id);
        $this->assertSame(
            1,
            Attendance::query()
                ->where('session_id', $session->session_id)
                ->where('user_id', $student->user_id)
                ->count()
        );
    }

    public function test_create_or_update_for_person_heals_legacy_user_only_absent_row(): void
    {
        $this->assertTrue(Schema::hasColumn('attendance', 'person_id'));

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'legacy-heal-admin@example.com']);
        $student = $this->createUser(['email' => 'legacy-heal-student@example.com']);
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse(['title' => 'Legacy Heal Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assertNotNull($student->person_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Legacy Heal Meeting',
            'session_date' => now()->toDateString(),
        ]);

        $legacy = Attendance::create([
            'user_id' => $student->user_id,
            'person_id' => null,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Absent',
            'attendance_time' => now(),
        ]);
        $this->assertNull($legacy->fresh()->person_id);

        $updated = app(AttendanceCloseService::class)->createOrUpdateForPerson(
            $session,
            (int) $student->person_id,
            'Present',
            (int) $admin->user_id,
        );

        $this->assertSame((int) $legacy->attendance_id, (int) $updated->attendance_id);
        $this->assertSame('Present', $updated->status);
        $this->assertSame((int) $student->person_id, (int) $updated->person_id);
        $this->assertSame(
            1,
            Attendance::query()
                ->where('session_id', $session->session_id)
                ->where(function ($query) use ($student) {
                    $query->where('user_id', $student->user_id)
                        ->orWhere('person_id', $student->person_id);
                })
                ->count()
        );
    }
}
