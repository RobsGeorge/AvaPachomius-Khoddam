<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\UserNotification;
use App\Services\NotificationGeneratorService;
use App\Services\NotificationPreferenceService;
use App\Services\NotificationScannerService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class NotificationHubTest extends EventModuleTestCase
{
    public function test_hub_page_loads_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-student@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(__('notifications.hub_title'));
    }

    public function test_unread_count_reflects_unread_notifications(): void
    {
        $student = $this->createUser([
            'email' => 'notif-count@example.com',
            'application_status' => 'approved',
            'registration_completed' => true,
        ]);

        UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'assignment_deadline',
            'title' => 'Test deadline',
            'body' => 'Due soon',
            'dedupe_key' => 'test:1',
        ]);

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Test deadline');
    }

    public function test_mark_all_read_clears_unread(): void
    {
        $student = $this->createUser(['email' => 'notif-read@example.com']);

        UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam soon',
            'body' => 'Tomorrow',
            'dedupe_key' => 'test:exam:1',
        ]);

        $this->actingAs($student)
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(0, UserNotification::query()->where('user_id', $student->user_id)->whereNull('read_at')->count());
    }

    public function test_mandatory_announcement_preference_cannot_disable_all_channels(): void
    {
        $student = $this->createUser(['email' => 'notif-mandatory@example.com']);
        $prefs = app(NotificationPreferenceService::class);
        $prefs->ensureDefaults($student);

        $this->expectException(ValidationException::class);

        $prefs->save($student, [
            'admin_announcement' => [
                'portal_enabled' => false,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
            ],
        ]);
    }

    public function test_deadline_scanner_creates_assignment_notifications(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-deadline@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        Assignment::create([
            'course_id' => $course->course_id,
            'assignment_name' => 'Due Tomorrow',
            'assignment_description' => 'Test assignment',
            'total_points' => 10,
            'due_date' => now()->addHours(12),
        ]);

        $count = app(NotificationScannerService::class)->scanDeadlines();

        $this->assertGreaterThan(0, $count);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('type', 'assignment_deadline')
                ->exists()
        );
    }

    public function test_generator_dispatches_email_when_enabled(): void
    {
        Mail::fake();

        $student = $this->createUser(['email' => 'notif-email@example.com']);
        $prefs = app(NotificationPreferenceService::class);
        $prefs->ensureDefaults($student);
        $prefs->save($student, [
            'assignment_deadline' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
            ],
        ]);

        app(NotificationGeneratorService::class)->createOrUpdate(
            $student,
            'assignment_deadline',
            'Test',
            'Body',
            route('notifications.index'),
            'assignment',
            1,
        );

        Mail::assertSent(\App\Mail\NotificationMail::class);
    }

    public function test_custom_reminder_fires(): void
    {
        $student = $this->createUser(['email' => 'notif-reminder@example.com']);

        \App\Models\UserNotificationReminder::create([
            'user_id' => $student->user_id,
            'title' => 'Study session',
            'body' => 'Review notes',
            'remind_at' => now()->subMinute(),
            'recurrence' => 'once',
            'channels' => ['portal'],
        ]);

        $count = app(NotificationScannerService::class)->fireDueReminders();

        $this->assertSame(1, $count);
        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'custom_reminder')
            ->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->action_url);

        $this->actingAs($student)
            ->get(route('notifications.show', $notification))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_custom_reminder_dedupe_key_uses_scheduled_occurrence(): void
    {
        $student = $this->createUser(['email' => 'notif-reminder-dedupe@example.com']);
        $remindAt = now()->subMinutes(2);

        $reminder = \App\Models\UserNotificationReminder::create([
            'user_id' => $student->user_id,
            'title' => 'Study session',
            'body' => 'Review notes',
            'remind_at' => $remindAt,
            'recurrence' => 'daily',
            'channels' => ['portal'],
        ]);

        $generator = app(NotificationGeneratorService::class);
        $dedupeKey = 'custom_reminder:'.$reminder->id.':'.$remindAt->format('Y-m-d-H-i');

        $generator->createOrUpdate(
            $student,
            'custom_reminder',
            $reminder->title,
            $reminder->body ?? '',
            null,
            'user_notification_reminder',
            $reminder->id,
            UserNotification::PRIORITY_NORMAL,
            [],
            $dedupeKey
        );
        $generator->createOrUpdate(
            $student,
            'custom_reminder',
            $reminder->title,
            $reminder->body ?? '',
            null,
            'user_notification_reminder',
            $reminder->id,
            UserNotification::PRIORITY_NORMAL,
            [],
            $dedupeKey
        );

        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('type', 'custom_reminder')
                ->count()
        );
    }

    public function test_whatsapp_service_records_delivery_when_configured(): void
    {
        config([
            'notifications.whatsapp.api_url' => 'https://graph.facebook.com/v18.0',
            'notifications.whatsapp.api_token' => 'test-token',
            'notifications.whatsapp.phone_number_id' => '12345',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $student = $this->createUser([
            'email' => 'notif-wa@example.com',
            'mobile_number' => '1012345678',
        ]);

        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'assignment_deadline',
            'title' => 'WhatsApp test',
            'body' => 'Body',
            'dedupe_key' => 'wa:test:1',
        ]);

        $delivery = app(\App\Services\WhatsAppNotificationService::class)->send($notification, $student);

        $this->assertSame(\App\Models\NotificationWhatsappDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame('wamid.test', $delivery->provider_message_id);
    }

    public function test_sessions_show_redirects_to_sessions_index_with_focus(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-session-admin@example.com']);
        $course = $this->createCourse();
        $session = \App\Models\Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Week 3 Lecture',
            'session_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('sessions.show', $session))
            ->assertRedirect(route('sessions.index', [
                'session_id' => $session->session_id,
            ]));
    }

    public function test_session_notification_link_reaches_close_attendance_page(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-session-follow@example.com']);
        $course = $this->createCourse();
        $session = \App\Models\Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Week 3 Lecture',
            'session_date' => now()->subDays(10)->toDateString(),
        ]);

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'session_unclosed',
            'title' => 'Close attendance',
            'body' => 'Session still open',
            'action_url' => route('sessions.show', $session),
            'dedupe_key' => "session_unclosed:{$session->session_id}",
        ]);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Week 3 Lecture')
            ->assertSee(__('pages.close_attendance'), false);
    }
}
