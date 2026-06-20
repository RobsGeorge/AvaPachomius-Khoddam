<?php

namespace Tests\Load\Events;

use App\Models\EventReservation;
use App\Services\EventReservationService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class EventReservationLoadTest extends EventModuleTestCase
{
    /**
     * Simulates high-volume sequential reservations against fixed capacity.
     */
    public function test_many_reservations_fill_capacity_and_waitlist(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'load@example.com']);
        $capacity = 50;
        $attempts = 120;

        $event = $this->createEvent($admin, ['capacity' => $capacity]);
        $service = app(EventReservationService::class);

        $users = [];
        for ($i = 0; $i < $attempts; $i++) {
            $user = $this->createUser(['email' => "load{$i}@example.com"]);
            $this->assignCourseRole($user, $course, $roles['student']);
            $users[] = $user;
        }

        $started = microtime(true);

        foreach ($users as $user) {
            $service->reserve($event->fresh(), $user);
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        $confirmed = EventReservation::where('event_id', $event->event_id)
            ->where('status', EventReservation::STATUS_CONFIRMED)
            ->count();
        $waitlist = EventReservation::where('event_id', $event->event_id)
            ->where('status', EventReservation::STATUS_WAITLIST)
            ->count();

        $this->assertSame($capacity, $confirmed);
        $this->assertSame($attempts - $capacity, $waitlist);
        $this->assertLessThan(30000, $elapsedMs, 'Load test exceeded 30s budget');
    }
}
