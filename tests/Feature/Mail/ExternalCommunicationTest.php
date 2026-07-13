<?php

namespace Tests\Feature\Mail;

use App\Mail\RoleAssignmentMail;
use App\Models\NotificationWhatsappDelivery;
use App\Models\RoleAssignmentEmailTemplate;
use App\Models\UserNotification;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * Coverage of outbound external communications: email (Mailables + Blade rendering)
 * and the WhatsApp Cloud API integration. External HTTP is always faked so tests
 * never contact a real provider.
 */
class ExternalCommunicationTest extends EventModuleTestCase
{
    // --- Email -------------------------------------------------------------

    public function test_role_assignment_email_is_sent_to_the_assignee(): void
    {
        Mail::fake();

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'mail-admin@example.com']);
        $assignee = $this->createUser(['email' => 'mail-assignee@example.com']);
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'manager', ['role.manage']);
        // Service-membership guard: the assignee must belong to the course's service.
        $this->ensureServiceMembership($assignee, $course);

        $this->actingAs($admin)
            ->post(route('courses.roles.assignments.store', $course), [
                'user_id' => $assignee->user_id,
                'role_id' => $role->role_id,
            ])
            ->assertRedirect();

        Mail::assertSent(
            RoleAssignmentMail::class,
            fn (RoleAssignmentMail $mail) => $mail->hasTo('mail-assignee@example.com')
        );
    }

    public function test_role_assignment_email_renders_with_substituted_placeholders(): void
    {
        $user = $this->createUser(['email' => 'render@example.com', 'first_name' => 'Mina']);

        $template = new RoleAssignmentEmailTemplate([
            'template_key' => RoleAssignmentEmailTemplate::KEY_COURSE_ROLE_ASSIGNED,
            'locale' => 'en',
            'subject' => 'You are now {{role_name}}',
            'body_html' => '<p>Hello {{name}}, welcome to {{course_title}}.</p>',
        ]);

        $mail = new RoleAssignmentMail($user, $template, [
            'name' => 'Mina',
            'role_name' => 'Manager',
            'course_title' => 'Deacons 101',
        ]);

        $rendered = $mail->render();

        $this->assertStringContainsString('Mina', $rendered);
        $this->assertStringContainsString('Deacons 101', $rendered);
        // The subject is computed from the template, not left as a raw placeholder.
        $this->assertStringNotContainsString('{{role_name}}', $rendered);
    }

    public function test_role_assignment_email_renders_under_arabic_locale(): void
    {
        $this->app->setLocale('ar');

        $user = $this->createUser(['email' => 'render-ar@example.com']);
        $template = new RoleAssignmentEmailTemplate([
            'template_key' => RoleAssignmentEmailTemplate::KEY_COURSE_ROLE_ASSIGNED,
            'locale' => 'ar',
            'subject' => 'تعيين دور',
            'body_html' => '<p>مرحبًا</p>',
        ]);

        $rendered = (new RoleAssignmentMail($user, $template, []))->render();

        $this->assertStringContainsString('مرحبًا', $rendered);
    }

    // --- WhatsApp (external HTTP) ------------------------------------------

    public function test_whatsapp_message_is_sent_when_configured(): void
    {
        Config::set('notifications.whatsapp.api_url', 'https://graph.example.test/v1');
        Config::set('notifications.whatsapp.api_token', 'test-token');
        Config::set('notifications.whatsapp.phone_number_id', '123456');

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.TEST']]], 200),
        ]);

        $user = $this->createUser(['email' => 'wa-user@example.com', 'mobile_number' => '01000000001']);
        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam soon',
            'body' => 'Tomorrow at 9',
            'dedupe_key' => 'wa:1',
        ]);

        $delivery = app(WhatsAppNotificationService::class)->send($notification, $user);

        $this->assertSame(NotificationWhatsappDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame('wamid.TEST', $delivery->provider_message_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '123456/messages')
                && $request['messaging_product'] === 'whatsapp'
                && str_starts_with($request['to'], '20');
        });
    }

    public function test_whatsapp_delivery_fails_gracefully_when_not_configured(): void
    {
        Config::set('notifications.whatsapp.api_url', null);
        Config::set('notifications.whatsapp.api_token', null);
        Config::set('notifications.whatsapp.phone_number_id', null);

        Http::fake();

        $user = $this->createUser(['email' => 'wa-off@example.com']);
        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam soon',
            'body' => 'Tomorrow',
            'dedupe_key' => 'wa:off:1',
        ]);

        $delivery = app(WhatsAppNotificationService::class)->send($notification, $user);

        $this->assertSame(NotificationWhatsappDelivery::STATUS_FAILED, $delivery->status);
        Http::assertNothingSent();
    }
}
