<?php

namespace Tests\Feature\Events;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventReservation;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class EventReservationFlowTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_user_can_reserve_and_cancel_with_audit_logs(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'flow@example.com']);
        $this->makeEventAdmin($admin);
        $event = $this->createEvent($admin);

        $this->actingAs($user)
            ->post(route('events.reserve', $event->event_id))
            ->assertRedirect(route('events.show', $event->event_id));

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->event_id,
            'user_id' => $user->user_id,
            'status' => EventReservation::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->user_id,
            'route_name' => 'events.action.reservation.reserve',
        ]);

        $log = ActivityLog::where('route_name', 'events.action.reservation.reserve')->first();
        $this->assertSame('events', $log->request_input['module'] ?? null);
        $this->assertSame('confirmed', $log->request_input['status'] ?? null);

        $this->actingAs($user)
            ->post(route('events.cancel', $event->event_id))
            ->assertRedirect(route('events.my-reservations'));

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'events.action.reservation.cancel',
        ]);
    }

    public function test_events_index_lists_visible_events(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'index@example.com']);
        $event = $this->createEvent($admin, ['title' => 'Visible Event']);

        $this->actingAs($user)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Visible Event');
    }

    public function test_ineligible_user_gets_error_on_reserve(): void
    {
        $admin = $this->createUser(['email' => 'inelig@example.com', 'is_superadmin' => true]);
        $event = $this->createEvent($admin, [
            'visibility' => 'role_based',
            'eligible_roles' => ['admin'],
        ]);

        $outsider = $this->createUser(['email' => 'outsider@example.com']);

        $this->actingAs($outsider)
            ->from(route('events.show', $event->event_id))
            ->post(route('events.reserve', $event->event_id))
            ->assertRedirect(route('events.show', $event->event_id));

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'events.action.reservation.reserve',
            'user_id' => $outsider->user_id,
        ]);
    }
}
