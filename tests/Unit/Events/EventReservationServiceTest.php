<?php

namespace Tests\Unit\Events;

use App\Models\EventReservation;
use App\Services\EventReservationService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class EventReservationServiceTest extends EventModuleTestCase
{
    private EventReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(EventReservationService::class);
    }

    public function test_reserve_confirms_when_capacity_available(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'cap@example.com']);
        $event = $this->createEvent($admin, ['capacity' => 5]);

        $result = $this->service->reserve($event, $user);

        $this->assertSame(EventReservation::STATUS_CONFIRMED, $result['status']);
        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->event_id,
            'user_id' => $user->user_id,
            'status' => EventReservation::STATUS_CONFIRMED,
        ]);
    }

    public function test_reserve_waitlists_when_full(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'full@example.com']);
        $event = $this->createEvent($admin, ['capacity' => 1]);

        $first = $this->createUser(['email' => 'first@example.com']);
        $second = $this->createUser(['email' => 'second@example.com']);
        $this->assignCourseRole($first, $course, $roles['student']);
        $this->assignCourseRole($second, $course, $roles['student']);

        $this->service->reserve($event, $first);
        $result = $this->service->reserve($event, $second);

        $this->assertSame(EventReservation::STATUS_WAITLIST, $result['status']);
    }

    public function test_cancel_promotes_next_waitlist(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'promo@example.com']);
        $event = $this->createEvent($admin, ['capacity' => 1]);

        $confirmed = $this->createUser(['email' => 'c@example.com']);
        $waitlisted = $this->createUser(['email' => 'w@example.com']);
        $this->assignCourseRole($confirmed, $course, $roles['student']);
        $this->assignCourseRole($waitlisted, $course, $roles['student']);

        $this->service->reserve($event, $confirmed);
        $this->service->reserve($event, $waitlisted);

        $this->service->cancel($event, $confirmed);

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->event_id,
            'user_id' => $waitlisted->user_id,
            'status' => EventReservation::STATUS_CONFIRMED,
        ]);
    }

    public function test_capacity_decrease_demotes_latest_confirmed(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'demote@example.com']);
        $event = $this->createEvent($admin, ['capacity' => 3]);

        $users = [];
        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $u = $this->createUser(['email' => $email]);
            $this->assignCourseRole($u, $course, $roles['student']);
            $this->service->reserve($event, $u);
            $users[] = $u;
        }

        $demoted = $this->service->applyCapacityDecrease($event->fresh(), 2);

        $this->assertCount(1, $demoted);
        $this->assertDatabaseHas('event_reservations', [
            'user_id' => $users[2]->user_id,
            'status' => EventReservation::STATUS_WAITLIST,
        ]);
    }

    public function test_cancel_all_for_event_cancels_active_reservations(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'cancelall@example.com']);
        $event = $this->createEvent($admin, ['capacity' => 2]);

        $user = $this->createUser(['email' => 'u@example.com']);
        $this->assignCourseRole($user, $course, $roles['student']);
        $this->service->reserve($event, $user);

        $count = $this->service->cancelAllForEvent($event, $admin);

        $this->assertSame(1, $count);
        $this->assertDatabaseMissing('event_reservations', [
            'event_id' => $event->event_id,
            'status' => EventReservation::STATUS_CONFIRMED,
        ]);
    }
}
