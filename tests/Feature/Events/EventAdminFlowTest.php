<?php

namespace Tests\Feature\Events;

use App\Models\ActivityLog;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class EventAdminFlowTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_event_admin_can_create_and_publish_event(): void
    {
        $admin = $this->createUser(['email' => 'evtadmin@example.com']);
        $this->makeEventAdmin($admin);

        $startsAt = now()->addDays(2);

        $response = $this->actingAs($admin)
            ->post(route('events.admin.store'), [
                'title' => 'New Conference',
                'description' => 'Annual meetup',
                'location' => 'Room 1',
                'starts_at' => $startsAt->format('Y-m-d\TH:i'),
                'ends_at' => $startsAt->copy()->addHours(3)->format('Y-m-d\TH:i'),
                'capacity' => 100,
                'visibility' => 'institution',
            ]);

        $event = Event::where('title', 'New Conference')->first();
        $this->assertNotNull($event);
        $response->assertRedirect(route('events.admin.edit', $event->event_id));
        $this->assertSame(Event::STATUS_DRAFT, $event->status);

        $this->actingAs($admin)
            ->post(route('events.admin.publish', $event->event_id))
            ->assertRedirect();

        $this->assertSame(Event::STATUS_PUBLISHED, $event->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'events.action.admin.publish',
        ]);
    }

    public function test_non_admin_cannot_access_event_admin_routes(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('events.admin.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_assign_event_admin(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'super@example.com']);
        $target = $this->createUser(['email' => 'target@example.com']);

        $this->actingAs($super)
            ->post(route('superadmin.event-admins.store'), ['user_id' => $target->user_id])
            ->assertRedirect(route('superadmin.index'));

        $this->assertDatabaseHas('event_admins', ['user_id' => $target->user_id]);
        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'events.action.admin.assign',
        ]);
    }
}
