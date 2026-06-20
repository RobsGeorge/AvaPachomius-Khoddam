<?php

namespace Tests\Feature\Events;

use App\Services\EventReservationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\EventModuleTestCase;

class EventCheckInFlowTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_confirmed_attendee_page_shows_qr_code(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'student@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'admin@example.com']);
        $event = $this->createEvent($admin);

        app(EventReservationService::class)->reserve($event, $student);

        $this->actingAs($student)
            ->get(route('events.show', $event->event_id))
            ->assertOk()
            ->assertSee('svg', false);
    }

    public function test_staff_can_check_in_via_signed_qr_url(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'checkin-student@example.com']);
        $staff = $this->createUser(['email' => 'checkin-staff@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->makeEventAdmin($staff);

        $event = $this->createEvent($staff, [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        app(EventReservationService::class)->reserve($event, $student);

        $url = $event->fresh()->signedCheckInUrlFor($student);

        $this->actingAs($staff)
            ->get($url)
            ->assertRedirect(route('events.admin.check-in', $event->event_id));

        $this->assertDatabaseHas('event_check_ins', [
            'event_id' => $event->event_id,
            'user_id' => $student->user_id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'events.action.check_in.record',
        ]);
    }

    public function test_expired_check_in_link_is_rejected(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'expired-student@example.com']);
        $staff = $this->createUser(['email' => 'expired-staff@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->makeEventAdmin($staff);

        $event = $this->createEvent($staff, [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        app(EventReservationService::class)->reserve($event, $student);

        $url = URL::temporarySignedRoute(
            'events.check-in.verify',
            now()->subMinute(),
            ['event' => $event->event_id, 'user' => $student->user_id]
        );

        $this->actingAs($staff)
            ->get($url)
            ->assertForbidden();
    }
}
