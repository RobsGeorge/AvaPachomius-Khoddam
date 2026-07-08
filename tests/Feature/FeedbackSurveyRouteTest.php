<?php

namespace Tests\Feature;

use Tests\Support\EventModuleTestCase;

class FeedbackSurveyRouteTest extends EventModuleTestCase
{
    public function test_create_survey_page_is_not_captured_by_show_route(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'feedback-create-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Feedback Route Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.create'))
            ->assertOk()
            ->assertSee(__('pages.feedback_create_survey'));
    }
}
