<?php

namespace Tests\Feature\UseCases\Applicant;

use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use Tests\Support\EventModuleTestCase;

/**
 * F-04 applicant experience (UC-AUTH-08, TC-APP-*). Covers the status timeline,
 * inline correction guidance for rejected fields, and the help/FAQ page.
 */
class ApplicantExperienceTest extends EventModuleTestCase
{
    public function test_status_page_shows_the_progress_timeline(): void
    {
        $user = $this->createUser([
            'email' => 'appl-pending@example.com',
            'application_status' => RegistrationApplication::STATUS_PENDING_REVIEW,
        ]);
        RegistrationApplication::create([
            'user_id' => $user->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['first_name' => 'مينا'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)->get(route('application.status'))
            ->assertOk()
            ->assertSee(__('registration_review.timeline_heading'))
            ->assertSee(__('registration_review.timeline_submitted'))
            ->assertSee(__('registration_review.timeline_decision_pending'));
    }

    public function test_correction_guidance_lists_rejected_fields_with_notes(): void
    {
        $user = $this->createUser([
            'email' => 'appl-correct@example.com',
            'application_status' => RegistrationApplication::STATUS_NEEDS_CORRECTION,
        ]);
        $application = RegistrationApplication::create([
            'user_id' => $user->user_id,
            'status' => RegistrationApplication::STATUS_NEEDS_CORRECTION,
            'snapshot' => ['national_id' => '123'],
            'submitted_at' => now(),
        ]);
        RegistrationApplicationFieldReview::create([
            'application_id' => $application->id,
            'field_key' => 'national_id',
            'status' => RegistrationApplicationFieldReview::STATUS_REJECTED,
            'comment' => 'Please re-check your national ID number.',
        ]);

        $this->actingAs($user)->get(route('application.status'))
            ->assertOk()
            ->assertSee(__('registration_review.correction_guidance_heading'))
            ->assertSee('Please re-check your national ID number.')
            ->assertSee(__('registration_review.timeline_decision_correction'));
    }

    public function test_help_faq_page_renders_localized_questions(): void
    {
        $user = $this->createUser(['email' => 'appl-help@example.com']);

        $response = $this->actingAs($user)->get(route('help.faq'))->assertOk();

        $faqs = (array) __('help.faqs');
        $response->assertSee($faqs[0]['q']);
    }

    public function test_help_page_requires_authentication(): void
    {
        $this->get(route('help.faq'))->assertRedirect(route('login'));
    }
}
