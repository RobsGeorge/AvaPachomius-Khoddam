<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\User;
use App\Services\FeedbackSurveyService;
use Tests\Support\EventModuleTestCase;

class FeedbackSurveyMcqOptionsTest extends EventModuleTestCase
{
    public function test_builder_offers_multiple_answers_and_other_textbox_options(): void
    {
        [$instructor, $survey] = $this->draftSurvey();

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.edit', $survey))
            ->assertOk()
            ->assertSee(__('pages.feedback_allow_multiple'), false)
            ->assertSee(__('pages.feedback_allow_other'), false);
    }

    public function test_instructor_can_add_a_multi_select_mcq_with_other_textbox(): void
    {
        [$instructor, $survey] = $this->draftSurvey();

        $this->actingAs($instructor)
            ->from(route('feedback.surveys.edit', $survey))
            ->post(route('feedback.surveys.questions.store', $survey), [
                'question_type' => FeedbackQuestion::TYPE_MCQ,
                'scope' => FeedbackQuestion::SCOPE_GENERAL,
                'label' => 'Which topics helped?',
                'is_required' => '1',
                'choices' => "Liturgy\nVisit\nTeaching",
                'allow_multiple' => '1',
                'allow_other' => '1',
            ])
            ->assertSessionHasNoErrors();

        $question = $survey->questions()->firstOrFail();
        $this->assertTrue($question->allowsMultiple());
        $this->assertTrue($question->allowsOther());
        $this->assertSame(['Liturgy', 'Visit', 'Teaching'], $question->choices());

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.edit', $survey))
            ->assertOk()
            ->assertSee(__('pages.feedback_badge_multiple'), false)
            ->assertSee(__('pages.feedback_badge_other'), false);
    }

    public function test_mcq_without_choices_is_rejected(): void
    {
        [$instructor, $survey] = $this->draftSurvey();

        $this->actingAs($instructor)
            ->from(route('feedback.surveys.edit', $survey))
            ->post(route('feedback.surveys.questions.store', $survey), [
                'question_type' => FeedbackQuestion::TYPE_MCQ,
                'scope' => FeedbackQuestion::SCOPE_GENERAL,
                'label' => 'Empty choices',
                'choices' => '',
            ])
            ->assertSessionHasErrors('choices');
    }

    public function test_student_can_select_multiple_answers_and_type_other(): void
    {
        [$instructor, $student, $survey, $question] = $this->openMcqSurvey(
            allowMultiple: true,
            allowOther: true,
        );

        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk()
            ->assertSee(__('pages.feedback_select_multiple_hint'), false)
            ->assertSee(__('pages.feedback_other_choice'), false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('name="answers_other['.$question->question_id.']"', false);

        $this->actingAs($student)
            ->from(route('feedback.surveys.show', $survey))
            ->post(route('feedback.surveys.submit', $survey), [
                'answers' => [
                    $question->question_id => ['Liturgy', FeedbackQuestion::OTHER_CHOICE],
                ],
                'answers_other' => [
                    $question->question_id => 'Hospital visit',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('feedback.surveys.show', $survey));

        $answer = FeedbackAnswer::query()->where('question_id', $question->question_id)->firstOrFail();
        $this->assertSame(['Liturgy', 'Hospital visit'], $answer->decodedValues());
        $this->assertSame('Liturgy · Hospital visit', $answer->displayValue());

        $aggregates = app(FeedbackSurveyService::class)->questionAggregates($survey->fresh()->load('questions'));
        $values = collect($aggregates[$question->question_id]['distribution'])->pluck('value')->all();
        $this->assertContains('Liturgy', $values);
        $this->assertContains('Hospital visit', $values);
        $this->assertNotContains(FeedbackQuestion::OTHER_CHOICE, $values);

        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk()
            ->assertSee('Liturgy · Hospital visit', false);
    }

    public function test_other_textbox_is_required_when_other_is_selected(): void
    {
        [, $student, $survey, $question] = $this->openMcqSurvey(
            allowMultiple: false,
            allowOther: true,
        );

        $this->actingAs($student)
            ->from(route('feedback.surveys.show', $survey))
            ->post(route('feedback.surveys.submit', $survey), [
                'answers' => [
                    $question->question_id => FeedbackQuestion::OTHER_CHOICE,
                ],
                'answers_other' => [
                    $question->question_id => '',
                ],
            ])
            ->assertSessionHasErrors('answers_other.'.$question->question_id);
    }

    public function test_single_choice_mcq_still_stores_one_string(): void
    {
        [, $student, $survey, $question] = $this->openMcqSurvey(
            allowMultiple: false,
            allowOther: false,
        );

        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk()
            ->assertSee('type="radio"', false)
            ->assertDontSee(__('pages.feedback_other_choice'), false);

        $this->actingAs($student)
            ->post(route('feedback.surveys.submit', $survey), [
                'answers' => [
                    $question->question_id => 'Visit',
                ],
            ])
            ->assertSessionHasNoErrors();

        $answer = FeedbackAnswer::query()->where('question_id', $question->question_id)->firstOrFail();
        $this->assertSame('Visit', $answer->value);
        $this->assertSame(['Visit'], $answer->decodedValues());
    }

    public function test_other_text_alone_counts_as_the_answer(): void
    {
        [, $student, $survey, $question] = $this->openMcqSurvey(
            allowMultiple: true,
            allowOther: true,
        );

        $this->actingAs($student)
            ->post(route('feedback.surveys.submit', $survey), [
                'answers' => [],
                'answers_other' => [
                    $question->question_id => 'Something else',
                ],
            ])
            ->assertSessionHasNoErrors();

        $answer = FeedbackAnswer::query()->where('question_id', $question->question_id)->firstOrFail();
        $this->assertSame(['Something else'], $answer->decodedValues());
    }

    /**
     * @return array{0: User, 1: FeedbackSurvey}
     */
    private function draftSurvey(): array
    {
        [$instructor, , $course, $module] = $this->staffAndStudent();

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'MCQ options survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_DRAFT,
            'is_mandatory' => false,
            'is_anonymous' => true,
        ]);

        return [$instructor, $survey];
    }

    /**
     * @return array{0: User, 1: User, 2: FeedbackSurvey, 3: FeedbackQuestion}
     */
    private function openMcqSurvey(bool $allowMultiple, bool $allowOther): array
    {
        [$instructor, $student, $course, $module] = $this->staffAndStudent();

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Open MCQ survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'is_anonymous' => true,
            'opened_at' => now(),
        ]);

        $question = FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_MCQ,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Which topics helped?',
            'order_index' => 1,
            'is_required' => true,
            'config' => [
                'choices' => ['Liturgy', 'Visit', 'Teaching'],
                'allow_multiple' => $allowMultiple,
                'allow_other' => $allowOther,
            ],
        ]);

        return [$instructor, $student, $survey, $question];
    }

    /**
     * @return array{0: User, 1: User, 2: Course, 3: Module}
     */
    private function staffAndStudent(): array
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'mcq-instructor@example.com']);
        $course = $this->createCourse(['title' => 'MCQ Survey Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $module = Module::create(['title' => 'MCQ module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'mcq-student@example.com',
            'first_name' => 'Mcq',
            'second_name' => 'Student',
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        return [$instructor, $student, $course, $module];
    }
}
