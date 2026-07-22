<?php

namespace Tests\Feature\Notifications;

use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * Round-trip coverage of in-portal notifications: a domain action SENDS a
 * notification to the correct recipient, and that recipient RECEIVES it in their
 * feed, can open it, and can clear it.
 */
class NotificationDeliveryTest extends EventModuleTestCase
{
    public function test_domain_action_sends_notification_to_the_target_user(): void
    {
        Mail::fake();

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-admin@example.com']);
        $assignee = $this->createUser(['email' => 'notif-target@example.com']);
        $course = $this->createCourse(['title' => 'Notify Course']);
        $role = $this->courseRoleWithPermissions($course, 'manager', ['role.manage']);
        // Service-membership guard: the assignee must belong to the course's service.
        $this->ensureServiceMembership($assignee, $course);

        $this->actingAs($admin)
            ->post(route('courses.roles.assignments.store', $course), [
                'user_id' => $assignee->user_id,
                'role_id' => $role->role_id,
            ])
            ->assertRedirect();

        // Sent to the assignee, not to the acting admin.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assignee->user_id,
            'type' => 'role_assigned',
        ]);
        $this->assertSame(
            0,
            UserNotification::query()->where('user_id', $admin->user_id)->where('type', 'role_assigned')->count()
        );
    }

    public function test_recipient_sees_unread_notification_in_their_feed(): void
    {
        $user = $this->createUser(['email' => 'feed-user@example.com']);

        UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'role_assigned',
            'title' => 'You have a new role',
            'body' => 'You are now a manager.',
            'dedupe_key' => 'notif:feed:1',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('You have a new role');
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_action_link(): void
    {
        $user = $this->createUser(['email' => 'open-notif@example.com']);

        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'role_assigned',
            'title' => 'Actionable',
            'body' => 'Go here',
            'action_url' => route('notifications.index'),
            'dedupe_key' => 'notif:open:1',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_open_another_users_notification(): void
    {
        $owner = $this->createUser(['email' => 'owner-notif@example.com']);
        $intruder = $this->createUser(['email' => 'intruder-notif@example.com']);

        $notification = UserNotification::create([
            'user_id' => $owner->user_id,
            'type' => 'role_assigned',
            'title' => 'Private',
            'body' => 'Secret',
            'dedupe_key' => 'notif:private:1',
        ]);

        $this->actingAs($intruder)
            ->get(route('notifications.show', $notification->id))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_the_unread_badge(): void
    {
        $user = $this->createUser(['email' => 'clear-notif@example.com']);

        UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam soon',
            'body' => 'Tomorrow',
            'dedupe_key' => 'notif:clear:1',
        ]);

        $this->actingAs($user)
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(
            0,
            UserNotification::query()->where('user_id', $user->user_id)->whereNull('read_at')->count()
        );
    }

    public function test_toggle_read_marks_notification_read_without_leaving_inbox(): void
    {
        $user = $this->createUser(['email' => 'toggle-read-notif@example.com']);

        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Toggle read',
            'body' => 'Unread item',
            'dedupe_key' => 'notif:toggle-read:1',
        ]);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.toggle-read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_toggle_read_marks_notification_unread_when_already_read(): void
    {
        $user = $this->createUser(['email' => 'toggle-unread-notif@example.com']);

        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Toggle unread',
            'body' => 'Read item',
            'read_at' => now(),
            'dedupe_key' => 'notif:toggle-unread:1',
        ]);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.toggle-read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success');

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notification_index_shows_toggle_read_button(): void
    {
        $user = $this->createUser(['email' => 'toggle-btn-notif@example.com']);

        UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Button test',
            'body' => 'Has toggle',
            'dedupe_key' => 'notif:toggle-btn:1',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('notification-read-toggle', false)
            ->assertSee(__('notifications.mark_read'), false);
    }
}
