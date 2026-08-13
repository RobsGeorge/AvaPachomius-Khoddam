<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\EventModuleTestCase;

/**
 * GET / is the T10c public homepage. When unpublished, guests go to login and
 * signed-in users go to course select — without bouncing back into a redirect loop.
 */
class HomeLandingTest extends EventModuleTestCase
{
    public function test_guest_is_redirected_to_login_when_homepage_is_unpublished(): void
    {
        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_login_hop_from_root_does_not_loop(): void
    {
        $response = $this->get(route('home'));
        $response->assertRedirect(route('login'));

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_authenticated_student_is_sent_to_course_select_when_homepage_is_unpublished(): void
    {
        $student = $this->createUser(['email' => 'home-student@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $this->actingAs($student)
            ->get(route('home'))
            ->assertRedirect(route('courses.select'));
    }

    public function test_unapproved_applicant_is_sent_to_course_select_when_homepage_is_unpublished(): void
    {
        $applicant = $this->createUser([
            'email' => 'home-applicant@example.com',
            'application_status' => User::APPLICATION_STATUS_PENDING_REVIEW,
            'registration_completed' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('home'))
            ->assertRedirect(route('courses.select'));
    }
}
