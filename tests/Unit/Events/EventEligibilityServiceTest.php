<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Models\EventReservationException;
use App\Services\EventEligibilityService;
use Tests\Support\EventModuleTestCase;

class EventEligibilityServiceTest extends EventModuleTestCase
{
    private EventEligibilityService $eligibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibility = app(EventEligibilityService::class);
    }

    public function test_institution_event_visible_to_any_enrolled_role(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'creator@example.com']);
        $event = $this->createEvent($admin, [
            'visibility' => 'institution',
            'eligible_roles' => [],
        ]);

        $this->assertTrue($this->eligibility->canView($user, $event));
        $this->assertTrue($this->eligibility->canReserve($user, $event));
    }

    public function test_role_based_requires_matching_role(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser();
        $this->assignCourseRole($student, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'admin@example.com']);
        $event = $this->createEvent($admin, [
            'visibility' => 'role_based',
            'eligible_roles' => ['instructor'],
        ]);

        $this->assertFalse($this->eligibility->canView($student, $event));

        $instructorRole = $this->createRole('instructor');
        $this->assignCourseRole($student, $course, $instructorRole);

        $this->assertTrue($this->eligibility->canView($student->fresh(['roles']), $event));
    }

    public function test_exception_grants_visibility(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'evtadmin@example.com']);
        $event = $this->createEvent($admin, [
            'visibility' => 'role_based',
            'eligible_roles' => ['admin'],
        ]);

        $this->assertFalse($this->eligibility->canView($user, $event));

        EventReservationException::create([
            'event_id' => $event->event_id,
            'user_id' => $user->user_id,
            'created_by_id' => $admin->user_id,
        ]);

        $this->assertTrue($this->eligibility->canView($user, $event));
    }

    public function test_draft_events_are_not_visible(): void
    {
        $admin = $this->createUser(['email' => 'draft@example.com']);
        $user = $this->createUser(['email' => 'viewer@example.com']);
        $event = $this->createEvent($admin, ['status' => Event::STATUS_DRAFT]);

        $this->assertFalse($this->eligibility->canView($user, $event));
    }

    public function test_visible_events_filters_collection(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $user = $this->createUser();
        $this->assignCourseRole($user, $course, $roles['student']);

        $admin = $this->createUser(['email' => 'list@example.com']);
        $visible = $this->createEvent($admin, ['title' => 'Open']);
        $this->createEvent($admin, [
            'title' => 'Hidden',
            'visibility' => 'role_based',
            'eligible_roles' => ['admin'],
        ]);

        $titles = $this->eligibility->visibleEvents($user)->pluck('title')->all();

        $this->assertSame(['Open'], $titles);
        $this->assertTrue($visible->is($this->eligibility->visibleEvents($user)->first()));
    }
}
