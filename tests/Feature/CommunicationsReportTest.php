<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CommunicationLog;
use App\Models\UserNotification;
use App\Services\AnnouncementService;
use App\Services\NotificationGeneratorService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class CommunicationsReportTest extends EventModuleTestCase
{
    public function test_staff_with_permission_can_view_report(): void
    {
        Mail::fake();

        $course = $this->createCourse();
        $adminRole = $this->courseRoleWithPermissions($course, 'comms-admin', ['communications.report']);
        $admin = $this->createUser(['email' => 'comms-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->get(route('communications.report'))
            ->assertOk()
            ->assertSee(__('communications.title'));
    }

    public function test_student_without_permission_is_forbidden(): void
    {
        $course = $this->createCourse();
        $studentRole = $this->courseRoleWithPermissions($course, 'comms-student', ['announcement.view']);
        $student = $this->createUser(['email' => 'comms-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->get(route('communications.report'))
            ->assertForbidden();
    }

    public function test_portal_and_email_notifications_are_logged(): void
    {
        Mail::fake();

        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'logged-student@example.com']);
        $studentRole = $this->courseRoleWithPermissions($course, 'c-student', ['course.access']);
        $this->assignCourseRole($student, $course, $studentRole);

        $prefs = app(\App\Services\NotificationPreferenceService::class);
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
            'Deadline soon',
            'Please submit',
            route('dashboard'),
            'assignment',
            99,
            UserNotification::PRIORITY_NORMAL,
            ['course_id' => $course->course_id],
        );

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_PORTAL,
            'status' => CommunicationLog::STATUS_SENT,
            'course_id' => $course->course_id,
            'subject' => 'Deadline soon',
        ]);

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'recipient_email' => 'logged-student@example.com',
            'course_id' => $course->course_id,
        ]);
    }

    public function test_missing_email_is_logged_as_skipped(): void
    {
        Mail::fake();

        $student = $this->createUser(['email' => '']);
        // Force empty email after create defaults
        $student->email = '';
        $student->save();

        $prefs = app(\App\Services\NotificationPreferenceService::class);
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
            'No email',
            'Body',
            null,
            'assignment',
            100,
        );

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SKIPPED,
        ]);
    }

    public function test_email_open_pixel_records_opened_at(): void
    {
        $student = $this->createUser(['email' => 'open@example.com']);
        $log = CommunicationLog::create([
            'user_id' => $student->user_id,
            'recipient_name' => $student->displayName(),
            'recipient_email' => $student->email,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Hello',
            'tracking_token' => 'opentoken123456789abcdefghijklmnopqrstuvwxyz',
            'sent_at' => now(),
        ]);

        $this->get(route('communications.track-open', ['token' => $log->tracking_token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertNotNull($log->fresh()->opened_at);
    }

    public function test_portal_mark_read_updates_communication_log(): void
    {
        $student = $this->createUser(['email' => 'readlog@example.com']);
        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam',
            'body' => 'Soon',
            'dedupe_key' => 'exam:read:1',
        ]);

        CommunicationLog::create([
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_PORTAL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Exam',
            'related_type' => UserNotification::class,
            'related_id' => $notification->id,
            'sent_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('notifications.show', $notification))
            ->assertRedirect();

        $log = CommunicationLog::query()
            ->where('related_type', UserNotification::class)
            ->where('related_id', $notification->id)
            ->first();

        $this->assertNotNull($log?->read_at);
        $this->assertNotNull($log?->opened_at);
    }

    public function test_announcement_publish_logs_announcement_and_email(): void
    {
        Mail::fake();

        $course = $this->createCourse();
        $adminRole = $this->courseRoleWithPermissions($course, 'ann-admin', [
            'announcement.manage', 'announcement.publish', 'communications.report', 'roster.view',
        ]);
        $admin = $this->createUser(['email' => 'ann-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'ann-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);

        $service = app(AnnouncementService::class);
        $announcement = $service->createDraft($admin, [
            'course_id' => $course->course_id,
            'title' => 'Important',
            'body' => 'Please read',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [
                Announcement::CHANNEL_HOMEPAGE => true,
                Announcement::CHANNEL_EMAIL => true,
            ],
        ]);

        $service->publish($announcement, $admin);

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_ANNOUNCEMENT,
            'status' => CommunicationLog::STATUS_SENT,
            'course_id' => $course->course_id,
            'subject' => 'Important',
        ]);

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $student->user_id,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'recipient_email' => 'ann-student@example.com',
            'course_id' => $course->course_id,
        ]);
    }

    public function test_report_filters_by_person_channel_and_month(): void
    {
        $course = $this->createCourse();
        $adminRole = $this->courseRoleWithPermissions($course, 'filter-admin', ['communications.report']);
        $admin = $this->createUser(['email' => 'filter-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $alice = $this->createUser(['email' => 'alice@example.com', 'first_name' => 'Alice']);
        $bob = $this->createUser(['email' => 'bob@example.com', 'first_name' => 'Bob']);

        CommunicationLog::create([
            'user_id' => $alice->user_id,
            'recipient_name' => 'Alice',
            'recipient_email' => $alice->email,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Alice email',
            'course_id' => $course->course_id,
            'sent_at' => now()->startOfMonth()->addDays(2),
        ]);

        CommunicationLog::create([
            'user_id' => $bob->user_id,
            'recipient_name' => 'Bob',
            'recipient_mobile' => $bob->mobile_number,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'status' => CommunicationLog::STATUS_FAILED,
            'subject' => 'Bob whatsapp',
            'course_id' => $course->course_id,
            'sent_at' => now()->startOfMonth()->addDays(3),
        ]);

        $month = now()->format('Y-m');

        $this->actingAs($admin)
            ->get(route('communications.report', [
                'user_id' => $alice->user_id,
                'channel' => CommunicationLog::CHANNEL_EMAIL,
                'month' => $month,
            ]))
            ->assertOk()
            ->assertSee('Alice email')
            ->assertDontSee('Bob whatsapp');

        $this->actingAs($admin)
            ->get(route('communications.report', [
                'status' => CommunicationLog::STATUS_FAILED,
                'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            ]))
            ->assertOk()
            ->assertSee('Bob whatsapp')
            ->assertDontSee('Alice email');
    }

    public function test_csv_export_downloads(): void
    {
        $course = $this->createCourse();
        $adminRole = $this->courseRoleWithPermissions($course, 'export-admin', ['communications.report']);
        $admin = $this->createUser(['email' => 'export-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        CommunicationLog::create([
            'user_id' => $admin->user_id,
            'recipient_name' => $admin->displayName(),
            'channel' => CommunicationLog::CHANNEL_PORTAL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Export me',
            'course_id' => $course->course_id,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('communications.report.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_whatsapp_send_creates_communication_log(): void
    {
        Config::set('notifications.whatsapp.api_url', 'https://graph.example.test/v1');
        Config::set('notifications.whatsapp.api_token', 'test-token');
        Config::set('notifications.whatsapp.phone_number_id', '123456');

        Http::fake([
            'graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        $user = $this->createUser([
            'email' => 'wa@example.com',
            'mobile_number' => '01012345678',
        ]);
        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'admin_announcement',
            'title' => 'WA title',
            'body' => 'WA body',
            'dedupe_key' => 'wa:1',
            'metadata' => ['course_id' => 1],
        ]);

        app(WhatsAppNotificationService::class)->send($notification, $user);

        $this->assertDatabaseHas('communication_logs', [
            'user_id' => $user->user_id,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'status' => CommunicationLog::STATUS_SENT,
            'provider_message_id' => 'wamid.123',
        ]);
    }

    public function test_course_scoped_admin_cannot_see_other_course_logs(): void
    {
        $courseA = $this->createCourse(['title' => 'Course A']);
        $courseB = $this->createCourse(['title' => 'Course B']);

        $adminRole = $this->courseRoleWithPermissions($courseA, 'scoped-admin', ['communications.report']);
        $admin = $this->createUser(['email' => 'scoped@example.com']);
        $this->assignCourseRole($admin, $courseA, $adminRole);

        $student = $this->createUser(['email' => 'other-course@example.com']);

        CommunicationLog::create([
            'user_id' => $student->user_id,
            'recipient_name' => 'Other',
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Secret B',
            'course_id' => $courseB->course_id,
            'sent_at' => now(),
        ]);

        CommunicationLog::create([
            'user_id' => $student->user_id,
            'recipient_name' => 'Visible',
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'status' => CommunicationLog::STATUS_SENT,
            'subject' => 'Visible A',
            'course_id' => $courseA->course_id,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('communications.report'))
            ->assertOk()
            ->assertSee('Visible A')
            ->assertDontSee('Secret B');
    }

    public function test_language_files_include_communications_parity(): void
    {
        $en = require lang_path('en/communications.php');
        $ar = require lang_path('ar/communications.php');

        $this->assertSame(array_keys($en), array_keys($ar));
        $this->assertSame(array_keys($en['channels']), array_keys($ar['channels']));
        $this->assertSame(array_keys($en['statuses']), array_keys($ar['statuses']));
    }
}
