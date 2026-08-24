<?php

namespace Tests\Feature\UseCases\Exams;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\User;
use App\Services\ExamResultsVisibilityService;
use Tests\Support\EventModuleTestCase;

class ExamResultsAnnouncementTest extends EventModuleTestCase
{
    public function test_student_cannot_see_score_until_results_are_announced(): void
    {
        [$instructor, $student, $exam, $result] = $this->gradedExamFixture();

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $exam));
        $this->assertSame('pending_announcement', $visibility->hideReason($student, $exam));

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertSee(__('exams.score_pending_announcement'), false)
            ->assertDontSee(number_format((float) $result->score, 1).'%', false);

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $exam->refresh();
        $this->assertTrue($exam->areResultsAnnounced());
        $this->assertTrue($visibility->canStudentViewScore($student, $exam->fresh()));

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertSee(number_format((float) $result->score, 1).'%', false);
    }

    public function test_announced_score_stays_hidden_until_mandatory_module_survey_submitted(): void
    {
        [$instructor, $student, $exam, $result] = $this->gradedExamFixture();

        $survey = FeedbackSurvey::create([
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
            'title' => 'End module survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);
        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Comments',
            'order_index' => 1,
            'is_required' => true,
        ]);

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame('pending_feedback', $visibility->hideReason($student, $exam->fresh()));

        FeedbackSubmission::create([
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        $this->assertTrue($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame('visible', $visibility->hideReason($student, $exam->fresh()));
        $this->assertSame(80.0, (float) $result->fresh()->score);
    }

    public function test_non_mandatory_survey_does_not_block_announced_scores(): void
    {
        [$instructor, $student, $exam] = $this->gradedExamFixture();

        FeedbackSurvey::create([
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
            'title' => 'Optional pulse',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'opened_at' => now(),
        ]);

        $exam->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $instructor->user_id,
        ]);

        $this->assertTrue(
            app(ExamResultsVisibilityService::class)->canStudentViewScore($student, $exam->fresh())
        );
    }

    public function test_double_announce_is_rejected(): void
    {
        [$instructor, , $exam] = $this->gradedExamFixture();

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam->fresh()))
            ->assertStatus(409);
    }

    public function test_graded_student_sees_score_after_feedback_even_with_closed_leftover_survey(): void
    {
        [$instructor, $student, $exam, $result] = $this->gradedExamFixture();

        FeedbackSurvey::create([
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
            'title' => 'Old weekly survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_CLOSED,
            'is_mandatory' => true,
            'opened_at' => now()->subWeek(),
            'closed_at' => now()->subDay(),
        ]);

        $openSurvey = FeedbackSurvey::create([
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
            'title' => 'Module feedback',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        FeedbackSubmission::create([
            'survey_id' => $openSurvey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertTrue($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame('visible', $visibility->hideReason($student, $exam->fresh()));

        $score = number_format((float) $result->score, 1).'%';

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertSee($score, false);

        $this->actingAs($student)
            ->get(route('exams.attempt.confirmation', $result->schedule_id))
            ->assertOk()
            ->assertSee($score, false);
    }

    public function test_failing_grade_is_visible_after_announce_and_feedback(): void
    {
        [$instructor, $student, $exam, $result] = $this->gradedExamFixture(['score' => 40]);

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertTrue($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame(40.0, (float) $result->fresh()->score);

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertSee(number_format(40.0, 1).'%', false);
    }

    public function test_student_without_exam_result_cannot_see_score_after_announce(): void
    {
        [$instructor, $student, $exam] = $this->gradedExamFixture();

        ExamResult::query()
            ->where('exam_id', $exam->exam_id)
            ->where('user_id', $student->user_id)
            ->delete();

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame('pending_assessment', $visibility->hideReason($student, $exam->fresh()));

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertDontSee('80.0%', false)
            ->assertDontSee('80.00%', false);
    }

    public function test_open_mandatory_survey_still_hides_announced_score(): void
    {
        [$instructor, $student, $exam, $result] = $this->gradedExamFixture();

        $survey = FeedbackSurvey::create([
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
            'title' => 'Required module feedback',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->post(route('exams.grades.announce', $exam))
            ->assertRedirect();

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $exam->fresh()));
        $this->assertSame('pending_feedback', $visibility->hideReason($student, $exam->fresh()));

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertRedirect(route('feedback.surveys.show', $survey->survey_id));

        $this->actingAs($student)
            ->get(route('exams.attempt.confirmation', $result->schedule_id))
            ->assertRedirect(route('feedback.surveys.show', $survey->survey_id));
    }

    /**
     * @return array{0: User, 1: User, 2: Exam, 3: ExamResult}
     */
    private function gradedExamFixture(array $resultOverrides = []): array
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');
        $instructor = $this->createUser(['email' => 'announce-instructor@example.com']);
        $student = $this->createUser(['email' => 'announce-student@example.com']);
        $course = $this->createCourse(['title' => 'Announce Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Module 1', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, ['status' => 'ended', 'feedback_open' => true]);

        $exam = Exam::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'exam_name' => 'Module exam',
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => 30,
            'total_points' => 10,
            'passing_score' => 50,
            'is_published' => true,
        ]);

        $schedule = ExamSchedule::create([
            'exam_id' => $exam->exam_id,
            'scheduled_date' => now()->subDay(),
            'is_completed' => true,
        ]);

        $result = ExamResult::create(array_merge([
            'exam_id' => $exam->exam_id,
            'schedule_id' => $schedule->schedule_id,
            'user_id' => $student->user_id,
            'score' => 80,
            'status' => ExamResult::STATUS_GRADED,
            'submitted_at' => now()->subHour(),
        ], $resultOverrides));

        return [$instructor, $student, $exam, $result];
    }
}
