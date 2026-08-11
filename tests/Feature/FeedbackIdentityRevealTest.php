<?php

namespace Tests\Feature;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Services\FeedbackIdentityRevealService;
use Tests\Support\EventModuleTestCase;

class FeedbackIdentityRevealTest extends EventModuleTestCase
{
    public function test_report_hides_student_names_by_default(): void
    {
        [$instructor, $student, $survey, $submission] = $this->surveyWithSubmission();

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.report', $survey))
            ->assertOk()
            ->assertSee(__('pages.feedback_anonymous_response', ['id' => $submission->submission_id]), false)
            ->assertDontSee($student->displayName(), false);
    }

    public function test_instructor_can_request_reveal_and_only_sees_name_after_superadmin_approves(): void
    {
        [$instructor, $student, $survey, $submission] = $this->surveyWithSubmission();
        $otherInstructor = $this->createUser(['email' => 'other-instructor@example.com']);
        $this->assignCourseRole($otherInstructor, $survey->course, $this->createRole('instructor'));
        $superadmin = $this->createUser([
            'email' => 'reveal-super@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.report.reveal', [$survey, $submission]), [
                'reason' => 'Critical pastoral follow-up needed urgently.',
            ])
            ->assertRedirect();

        $request = FeedbackIdentityRevealRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(FeedbackIdentityRevealRequest::STATUS_PENDING, $request->status);

        $this->actingAs($superadmin)
            ->post(route('superadmin.feedback-reveal.approve', $request))
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(FeedbackIdentityRevealRequest::STATUS_APPROVED, $request->status);
        $this->assertTrue(
            app(FeedbackIdentityRevealService::class)->viewerCanSeeIdentity($instructor, $submission)
        );
        $this->assertFalse(
            app(FeedbackIdentityRevealService::class)->viewerCanSeeIdentity($otherInstructor, $submission)
        );

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.report.submission', [$survey, $submission]))
            ->assertOk()
            ->assertSee($student->displayName(), false);

        $this->actingAs($otherInstructor)
            ->get(route('feedback.surveys.report.submission', [$survey, $submission]))
            ->assertOk()
            ->assertDontSee($student->displayName(), false)
            ->assertSee(__('pages.feedback_anonymous_response', ['id' => $submission->submission_id]), false);
    }

    public function test_legacy_student_report_url_redirects_to_submission_route(): void
    {
        [$instructor, $student, $survey, $submission] = $this->surveyWithSubmission();

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.report.student', [$survey, $student->user_id]))
            ->assertRedirect(route('feedback.surveys.report.submission', [$survey, $submission]));
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\User, 2: FeedbackSurvey, 3: FeedbackSubmission}
     */
    private function surveyWithSubmission(): array
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');
        $instructor = $this->createUser(['email' => 'reveal-instructor@example.com']);
        $student = $this->createUser([
            'email' => 'reveal-student@example.com',
            'first_name' => 'Secret',
            'second_name' => 'Respondent',
        ]);
        $course = $this->createCourse(['title' => 'Reveal Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Module Reveal', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, ['status' => 'ended', 'feedback_open' => true]);

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Anonymous survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'opened_at' => now(),
        ]);

        $question = FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'How was it?',
            'order_index' => 1,
            'is_required' => true,
        ]);

        $submission = FeedbackSubmission::create([
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        FeedbackAnswer::create([
            'submission_id' => $submission->submission_id,
            'question_id' => $question->question_id,
            'value' => 'Needs urgent follow-up',
        ]);

        $survey->setRelation('course', $course);

        return [$instructor, $student, $survey, $submission];
    }
}
