<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\EventModuleTestCase;

class HomeLandingTest extends EventModuleTestCase
{
    public function test_guest_sees_login_at_root_without_redirect(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_authenticated_student_is_sent_to_portal_from_root(): void
    {
        $student = $this->createUser(['email' => 'home-student@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $this->actingAs($student)
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_unapproved_applicant_is_sent_to_application_status_from_root(): void
    {
        $applicant = $this->createUser([
            'email' => 'home-applicant@example.com',
            'application_status' => User::APPLICATION_STATUS_PENDING_REVIEW,
            'registration_completed' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('home'))
            ->assertRedirect(route('application.status'));
    }
}
