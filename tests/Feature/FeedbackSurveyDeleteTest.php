<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\User;
use Tests\Support\EventModuleTestCase;

class FeedbackSurveyDeleteTest extends EventModuleTestCase
{
    public function test_instructor_can_delete_an_open_survey(): void
    {
        [$instructor, $student, $course, $survey] = $this->openSurveyFixture();

        $this->actingAs($instructor)
            ->get(route('feedback.index'))
            ->assertOk()
            ->assertSee(__('pages.delete_survey'), false);

        $this->actingAs($instructor)
            ->delete(route('feedback.surveys.destroy', $survey))
            ->assertRedirect(route('feedback.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('feedback_surveys', [
            'survey_id' => $survey->survey_id,
        ]);
        $this->assertTrue(
            ActivityLog::query()->where('route_name', 'feedback.survey_deleted')->exists()
        );

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertDontSee($survey->title);
    }

    public function test_instructor_can_delete_an_open_survey_that_has_responses(): void
    {
        [$instructor, $student, , $survey] = $this->openSurveyFixture();

        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Comments',
            'order_index' => 1,
            'is_required' => true,
        ]);
        FeedbackSubmission::create([
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.edit', $survey))
            ->assertOk()
            ->assertSee(__('pages.delete_survey'), false);

        $this->actingAs($instructor)
            ->delete(route('feedback.surveys.destroy', $survey))
            ->assertRedirect(route('feedback.index'));

        $this->assertDatabaseMissing('feedback_surveys', ['survey_id' => $survey->survey_id]);
        $this->assertDatabaseMissing('feedback_submissions', [
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
        ]);
    }

    public function test_closed_survey_cannot_be_deleted(): void
    {
        [$instructor, , , $survey] = $this->openSurveyFixture();
        $survey->update([
            'status' => FeedbackSurvey::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->get(route('feedback.index'))
            ->assertOk()
            ->assertDontSee(__('pages.delete_survey'), false);

        $this->actingAs($instructor)
            ->delete(route('feedback.surveys.destroy', $survey->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('survey');

        $this->assertDatabaseHas('feedback_surveys', [
            'survey_id' => $survey->survey_id,
            'status' => FeedbackSurvey::STATUS_CLOSED,
        ]);
    }

    public function test_student_cannot_delete_an_open_survey(): void
    {
        [, $student, $course, $survey] = $this->openSurveyFixture();

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertDontSee(__('pages.delete_survey'), false);

        $this->actingAs($student)
            ->delete(route('feedback.surveys.destroy', $survey))
            ->assertForbidden();

        $this->assertDatabaseHas('feedback_surveys', [
            'survey_id' => $survey->survey_id,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Course, 3: FeedbackSurvey}
     */
    private function openSurveyFixture(): array
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');
        $instructor = $this->createUser(['email' => 'survey-del-instructor@example.com']);
        $student = $this->createUser(['email' => 'survey-del-student@example.com']);
        $course = $this->createCourse(['title' => 'Survey Delete Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Delete Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Open module survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'opened_at' => now(),
        ]);

        return [$instructor, $student, $course, $survey];
    }
}
