<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\Session;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationScannerService;
use App\Services\SessionNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class SessionUpcomingNotificationTest extends EventModuleTestCase
{
    private function attachModule($course): Module
    {
        $module = Module::create(['title' => 'Week 1', 'description' => 'Intro']);
        DB::table('course_module')->insert([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
        ]);

        return $module;
    }

    /** @return array{staff: \App\Models\User, course: \App\Models\Course, session: Session, students: array<int, \App\Models\User>} */
    private function seedNotifiableSession(array $sessionOverrides = []): array
    {
        // Freeze the clock at midday so "tomorrow 09:00" is deterministically inside the
        // 24h upcoming-scan window, independent of the wall-clock time the suite runs at.
        $this->travelTo(now()->startOfDay()->addHours(12));

        $roles = $this->seedBasicRoles();
        $staff = $this->createUser(['email' => 'session-notify-staff@example.com', 'is_superadmin' => true]);
        $course = $this->createCourse(['title' => 'Notify Course']);
        $module = $this->attachModule($course);

        $session = Session::create(array_merge([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Thursday Lecture',
            'session_date' => now()->addDay()->toDateString(),
            'session_start_time' => '09:00:00',
            'notify_students' => true,
        ], $sessionOverrides));

        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "session-notify-student-{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $roles['student']);
            $students[] = $student;
        }

        return compact('staff', 'course', 'session', 'students');
    }

    public function test_scanner_creates_session_upcoming_notifications(): void
    {
        Mail::fake();
        ['session' => $session, 'students' => $students] = $this->seedNotifiableSession();

        $count = app(NotificationScannerService::class)->scanDeadlines();

        $this->assertGreaterThan(0, $count);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
        $this->assertStringContainsString(
            (string) $session->session_id,
            (string) UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->value('dedupe_key')
        );
    }

    public function test_scanner_skips_sessions_with_notify_students_off(): void
    {
        ['students' => $students] = $this->seedNotifiableSession(['notify_students' => false]);

        app(NotificationScannerService::class)->scanDeadlines();

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
    }

    public function test_scanner_notifies_only_targeted_students(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedNotifiableSession();

        DB::table('session_notification_targets')->insert([
            'session_id' => $session->session_id,
            'user_id' => $students[0]->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(NotificationScannerService::class)->scanDeadlines();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $students[1]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
    }

    public function test_scanner_skips_past_sessions(): void
    {
        ['students' => $students] = $this->seedNotifiableSession([
            'session_date' => now()->subDays(2)->toDateString(),
        ]);

        app(NotificationScannerService::class)->scanDeadlines();

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
    }

    public function test_staff_can_manually_notify_students(): void
    {
        Mail::fake();
        ['staff' => $staff, 'session' => $session, 'students' => $students] = $this->seedNotifiableSession();

        $this->actingAs($staff)
            ->post(route('sessions.notify-students', $session))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->where('dedupe_key', "session_upcoming:{$session->session_id}:user:{$students[0]->user_id}:manual")
                ->exists()
        );

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'sessions.action.notify_students',
        ]);
    }

    public function test_student_cannot_manually_notify_session(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedNotifiableSession();

        $this->actingAs($students[0])
            ->post(route('sessions.notify-students', $session))
            ->assertForbidden();
    }

    public function test_manual_notify_returns_warning_when_notify_students_disabled(): void
    {
        ['staff' => $staff, 'session' => $session] = $this->seedNotifiableSession(['notify_students' => false]);

        $this->actingAs($staff)
            ->post(route('sessions.notify-students', $session))
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_manual_notify_returns_warning_for_past_session(): void
    {
        ['staff' => $staff, 'session' => $session] = $this->seedNotifiableSession([
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($staff)
            ->post(route('sessions.notify-students', $session))
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_notify_next_session_uses_current_course_context(): void
    {
        Mail::fake();
        ['staff' => $staff, 'course' => $course, 'session' => $session, 'students' => $students] = $this->seedNotifiableSession();

        $otherCourse = $this->createCourse(['title' => 'Other Course']);
        $otherModule = $this->attachModule($otherCourse);
        Session::create([
            'course_id' => $otherCourse->course_id,
            'module_id' => $otherModule->module_id,
            'session_title' => 'Other Soon',
            'session_date' => now()->addHours(6)->toDateString(),
            'notify_students' => true,
        ]);

        app(CourseContextService::class)->setCurrentCourse($staff, $course->course_id);

        $this->actingAs($staff)
            ->post(route('sessions.notify-next'))
            ->assertRedirect(route('sessions.index'))
            ->assertSessionHas('success');

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->where('dedupe_key', "session_upcoming:{$session->session_id}:user:{$students[0]->user_id}:manual")
                ->exists()
        );
    }

    public function test_notify_next_session_warns_when_none_available(): void
    {
        $staff = $this->createUser(['email' => 'no-next@example.com', 'is_superadmin' => true]);
        $course = $this->createCourse();
        app(CourseContextService::class)->setCurrentCourse($staff, $course->course_id);

        $this->actingAs($staff)
            ->post(route('sessions.notify-next'))
            ->assertRedirect(route('sessions.index'))
            ->assertSessionHas('warning');
    }

    public function test_toggle_notify_students_updates_session_flag(): void
    {
        ['staff' => $staff, 'session' => $session] = $this->seedNotifiableSession();

        $this->actingAs($staff)
            ->patch(route('sessions.toggle-notify', $session), ['notify_students' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($session->fresh()->shouldNotifyStudents());

        $this->actingAs($staff)
            ->patch(route('sessions.toggle-notify', $session), ['notify_students' => true])
            ->assertRedirect();

        $this->assertTrue($session->fresh()->shouldNotifyStudents());
    }

    public function test_student_settings_page_shows_session_upcoming_type(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'session-prefs@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        $this->actingAs($student)
            ->get(route('notifications.settings'))
            ->assertOk()
            ->assertSee(__('notifications.types.session_upcoming'), false);
    }

    public function test_session_notification_link_opens_sessions_focus_view(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'session-link@example.com']);
        ['session' => $session] = $this->seedNotifiableSession();

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'session_upcoming',
            'title' => 'Upcoming session',
            'body' => 'Soon',
            'action_url' => route('sessions.index', ['session_id' => $session->session_id]),
            'dedupe_key' => "session_upcoming:{$session->session_id}:user:{$admin->user_id}:manual",
        ]);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Thursday Lecture', false);
    }

    public function test_scanner_respects_student_lead_hours_preference(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedNotifiableSession([
            'session_date' => now()->addDays(3)->toDateString(),
        ]);

        $prefs = app(NotificationPreferenceService::class);
        $prefs->ensureDefaults($students[0]);
        $prefs->save($students[0], [
            'session_upcoming' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => ['lead_hours' => 24],
            ],
        ]);

        app(NotificationScannerService::class)->scanDeadlines();

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );

        $session->update(['session_date' => now()->addHours(12)->toDateString()]);

        app(NotificationScannerService::class)->scanDeadlines();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'session_upcoming')
                ->exists()
        );
    }
}
