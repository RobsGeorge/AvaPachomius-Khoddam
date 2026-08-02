<?php

namespace Tests\Feature\Attendance;

use App\Billing\QuotaGuard;
use App\Models\Attendance;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Session;
use App\Models\User;
use App\Services\AttendanceCloseService;
use App\Services\AttendanceQrService;
use App\Services\Maturity\GuardianshipService;
use App\Support\People\PlacementMode;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * Attendance person_id expand — rung-0 marking, backfill, QR, guardian, seats.
 */
class AttendancePersonIdTest extends EventModuleTestCase
{
    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::main() ?? $this->createChurch(['slug' => 'att-person-main']);
        TenantContext::set($this->church);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_schema_has_nullable_person_id_and_user_id(): void
    {
        $this->assertTrue(Schema::hasColumn('attendance', 'person_id'));
        $this->assertTrue(Schema::hasColumn('attendance', 'user_id'));
    }

    public function test_rung0_person_without_user_can_be_marked_present_via_roster(): void
    {
        $admin = $this->createUser(['email' => 'att-person-admin@example.com']);
        $adminRole = $this->createRole('admin');
        $service = $this->createService(['church_id' => $this->church->church_id]);
        $course = $this->createCourse([
            'title' => 'Rung0 Course',
            'service_id' => $service->service_id,
            'church_id' => $this->church->church_id,
        ]);
        $this->assignCourseRole($admin, $course, $adminRole);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Today',
            'session_date' => now()->toDateString(),
        ]);

        $child = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'طفل',
            'second_name' => 'حضور',
            'third_name' => 'صفر',
            'date_of_birth' => '2018-01-01',
            'is_minor' => true,
        ]);

        PersonPlacement::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'person_id' => $child->person_id,
            'service_id' => $service->service_id,
            'course_id' => $course->course_id,
            'roster_status' => 'active',
            'placement_mode' => PlacementMode::INFO_ONLY,
        ]);

        $this->assertSame(0, User::query()->where('person_id', $child->person_id)->count());

        $this->actingAs($admin)
            ->postJson(route('sessions.attendance.store', $session), [
                'person_id' => $child->person_id,
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $row = Attendance::query()->where('session_id', $session->session_id)->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $child->person_id, (int) $row->person_id);
        $this->assertNull($row->user_id);
        $this->assertSame('Present', $row->status);
    }

    public function test_backfill_resolves_person_id_and_reports_unresolvable(): void
    {
        $admin = $this->createUser(['email' => 'att-bf-admin@example.com']);
        $student = $this->createUser(['email' => 'att-bf-student@example.com']);
        $this->assertNotNull($student->person_id);

        $course = $this->createCourse(['title' => 'Backfill Course']);
        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'S1',
            'session_date' => now()->toDateString(),
        ]);

        $ok = Attendance::create([
            'user_id' => $student->user_id,
            'person_id' => null,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
        ]);

        $orphanUser = User::create([
            'first_name' => 'Orphan',
            'second_name' => 'Att',
            'third_name' => 'X',
            'profile_photo' => '',
            'national_id' => '99999999999991',
            'mobile_number' => '01999999991',
            'email' => 'orphan-att@example.com',
            'job' => 'Student',
            'date_of_birth' => '2000-01-01',
            'password' => bcrypt('password'),
            'is_verified' => true,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
            'person_id' => null,
        ]);
        // Observer may create a person — force null for unresolvable case.
        User::query()->where('user_id', $orphanUser->user_id)->update(['person_id' => null]);

        $bad = Attendance::create([
            'user_id' => $orphanUser->user_id,
            'person_id' => null,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Absent',
            'attendance_time' => now(),
        ]);

        $report = 'docs/migrations/attendance-person-id-report-test.md';
        $exit = Artisan::call('attendance:backfill-person-id', [
            '--report' => $report,
        ]);

        $this->assertSame(1, $exit); // unresolvable present → FAILURE
        $this->assertSame((int) $student->person_id, (int) $ok->fresh()->person_id);
        $this->assertNull($bad->fresh()->person_id);

        $body = File::get(base_path($report));
        $this->assertStringContainsString('user_has_no_person_id', $body);
        $this->assertStringContainsString((string) $bad->attendance_id, $body);

        // Idempotent re-run still reports the same unresolvable row.
        Artisan::call('attendance:backfill-person-id', ['--report' => $report, '--dry-run' => true]);
        $this->assertSame((int) $student->person_id, (int) $ok->fresh()->person_id);
    }

    public function test_person_qr_marks_correct_person_without_user_credential(): void
    {
        $staff = $this->createUser(['email' => 'att-qr-staff@example.com']);
        $staffRole = $this->createRole('admin');
        $course = $this->createCourse(['title' => 'QR Course']);
        $this->assignCourseRole($staff, $course, $staffRole);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'QR Today',
            'session_date' => now()->toDateString(),
        ]);

        $person = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'سجل',
            'second_name' => 'فقط',
            'third_name' => 'أ',
            'date_of_birth' => '2017-05-01',
            'is_minor' => true,
        ]);

        $url = app(AttendanceQrService::class)->urlForPerson($person);
        $this->assertStringContainsString('person_id='.$person->person_id, $url);
        $this->assertStringContainsString('signature=', $url);

        $this->actingAs($staff)
            ->get($url)
            ->assertOk()
            ->assertSee('سجل', false);

        $this->actingAs($staff)
            ->post(route('attendance.record', $session), [
                'student_person_id' => $person->person_id,
            ])
            ->assertRedirect();

        $row = Attendance::query()->where('session_id', $session->session_id)->first();
        $this->assertSame((int) $person->person_id, (int) $row->person_id);
        $this->assertNull($row->user_id);
        $this->assertSame(0, User::query()->where('person_id', $person->person_id)->count());
    }

    public function test_guardian_check_in_writes_child_person_id_not_guardian_user_id(): void
    {
        $staffVerifier = $this->createUser(['email' => 'att-g-verifier@example.com']);
        $guardianUser = $this->createUser(['email' => 'att-g-guardian@example.com']);
        $guardianPerson = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'وصي',
            'second_name' => 'حضور',
            'third_name' => 'أ',
            'date_of_birth' => '1980-01-01',
            'is_minor' => false,
        ]);
        $guardianUser->forceFill(['person_id' => $guardianPerson->person_id])->save();

        $ward = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'قاصر',
            'second_name' => 'حضور',
            'third_name' => 'ب',
            'date_of_birth' => '2016-01-01',
            'is_minor' => true,
        ]);

        app(GuardianshipService::class)->linkGuardian(
            $guardianPerson,
            $ward,
            $staffVerifier,
            $this->church
        );

        $course = $this->createCourse(['title' => 'Guardian Course']);
        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Guardian Today',
            'session_date' => now()->toDateString(),
        ]);

        $this->actingAs($guardianUser)
            ->postJson(route('attendance.guardian.check-in', $session), [
                'person_id' => $ward->person_id,
                'status' => 'Present',
            ])
            ->assertOk();

        $row = Attendance::query()->where('session_id', $session->session_id)->first();
        $this->assertSame((int) $ward->person_id, (int) $row->person_id);
        $this->assertNull($row->user_id);
        $this->assertSame((int) $guardianUser->user_id, (int) $row->taken_by_id);
        $this->assertNotSame((int) $guardianUser->user_id, (int) $row->user_id);
    }

    public function test_attendance_marking_does_not_consume_billable_seat(): void
    {
        $church = $this->church;
        ChurchUser::query()->firstOrCreate(
            ['church_id' => $church->church_id, 'user_id' => $this->createUser(['email' => 'seat-a@example.com'])->user_id],
            ['status' => 'active', 'joined_at' => now()]
        );

        $before = app(QuotaGuard::class)->used($church, 'max_active_users');

        $admin = $this->createUser(['email' => 'seat-admin@example.com']);
        $course = $this->createCourse(['title' => 'Seat Course']);
        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Seat',
            'session_date' => now()->toDateString(),
        ]);

        $person = Person::withoutTenancy()->create([
            'church_id' => $church->church_id,
            'first_name' => 'Seat',
            'second_name' => 'Child',
            'third_name' => 'Z',
            'date_of_birth' => '2019-01-01',
            'is_minor' => true,
        ]);

        app(AttendanceCloseService::class)->createOrUpdateForPerson(
            $session,
            (int) $person->person_id,
            'Present',
            (int) $admin->user_id,
            allowNonEnrolled: true,
        );

        $after = app(QuotaGuard::class)->used($church->fresh(), 'max_active_users');
        $this->assertSame($before, $after);
        $this->assertFalse(
            ChurchUser::query()
                ->where('church_id', $church->church_id)
                ->whereIn('user_id', User::query()->where('person_id', $person->person_id)->pluck('user_id'))
                ->exists()
        );
    }

    public function test_legacy_user_id_roster_path_still_dual_writes_person_id(): void
    {
        $admin = $this->createUser(['email' => 'legacy-admin@example.com']);
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('Student');
        $course = $this->createCourse(['title' => 'Legacy Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $student = $this->createUser(['email' => 'legacy-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);
        $this->assertNotNull($student->person_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Legacy',
            'session_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('sessions.attendance.store', $session), [
                'user_id' => $student->user_id,
                'status' => 'Present',
            ])
            ->assertOk();

        $row = Attendance::query()->where('session_id', $session->session_id)->first();
        $this->assertSame((int) $student->user_id, (int) $row->user_id);
        $this->assertSame((int) $student->person_id, (int) $row->person_id);
    }
}
