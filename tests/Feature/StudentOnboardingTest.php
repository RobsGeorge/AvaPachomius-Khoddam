<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentOnboardingService;
use Tests\Support\EventModuleTestCase;

class StudentOnboardingTest extends EventModuleTestCase
{
    public function test_student_sees_onboarding_wizard_on_first_visit(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'onboard-student@example.com']);
        $course = $this->createCourse(['title' => 'Onboard Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('studentOnboardingModal', false)
            ->assertSee(__('onboarding.modal_title', [], 'en'));
    }

    public function test_onboarding_uses_arabic_when_locale_selected(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'onboard-ar-student@example.com']);
        $course = $this->createCourse(['title' => 'Onboard AR Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->withSession(['locale' => 'ar'])
            ->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('onboarding.modal_title', [], 'ar'));
    }

    public function test_student_can_complete_onboarding(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'onboard-complete@example.com']);
        $course = $this->createCourse(['title' => 'Complete Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->postJson(route('onboarding.complete'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $student->refresh();
        $this->assertNotNull($student->student_onboarding_completed_at);
        $this->assertFalse(app(StudentOnboardingService::class)->shouldShow($student));
    }

    public function test_completed_onboarding_is_not_shown_again(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'onboard-done@example.com',
            'student_onboarding_completed_at' => now(),
        ]);
        $course = $this->createCourse(['title' => 'Done Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('studentOnboardingModal', false);
    }

    public function test_instructor_does_not_see_onboarding_wizard(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'onboard-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Instructor Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $this->actingAs($instructor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('studentOnboardingModal', false);
    }
}
