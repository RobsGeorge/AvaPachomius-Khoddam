<?php

namespace Tests\Feature\UseCases\Journeys;

use App\Models\User;
use Tests\Support\EventModuleTestCase;

/**
 * Positive-access journeys (TC-JOURNEY-*): each configured persona can reach and operate the
 * surface their role grants — the complement to AuthorizationMatrixTest's denial dimension.
 */
class PersonaLandingAccessTest extends EventModuleTestCase
{
    public function test_event_admin_reaches_the_event_admin_console(): void
    {
        $user = $this->createUser(['email' => 'journey-eventadmin@example.com']);
        $this->makeEventAdmin($user);

        $this->actingAs($user)
            ->get(route('events.admin.index'))
            ->assertOk();
    }

    public function test_service_member_reaches_service_context(): void
    {
        $user = $this->createUser(['email' => 'journey-servicemember@example.com']);
        $service = $this->createService();
        $this->assignServiceRole($user, $service, allowCross: true);

        $this->actingAs($user)
            ->get(route('services.select'))
            ->assertOk();
    }

    public function test_applicant_can_view_their_application_status(): void
    {
        $applicant = $this->createUser([
            'email' => 'journey-applicant@example.com',
            'application_status' => User::APPLICATION_STATUS_PENDING_REVIEW,
            'registration_completed' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('application.status'))
            ->assertOk();
    }

    public function test_approved_student_reaches_the_portal(): void
    {
        $student = $this->createUser(['email' => 'journey-student@example.com']);

        // An approved, verified user is admitted to the portal (dashboard or a contextual
        // redirect, never a 4xx/5xx).
        $status = $this->actingAs($student)->get(route('dashboard'))->getStatusCode();

        $this->assertLessThan(400, $status, "Approved student was refused the dashboard (status $status)");
    }
}
