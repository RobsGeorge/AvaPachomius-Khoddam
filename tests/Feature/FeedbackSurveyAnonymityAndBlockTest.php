<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\ProjectMemberGrade;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ExamResultsVisibilityService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use App\Services\ProjectResultsVisibilityService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class FeedbackSurveyAnonymityAndBlockTest extends EventModuleTestCase
{
    public function test_blocking_survey_requires_an_assessment_from_the_module(): void
    {
        [$instructor, $course, $module] = $this->staffCourse();
        $exam = $this->makeExam($course, $module, 'Required exam');

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Missing target',
                'is_mandatory' => '1',
                'is_anonymous' => '1',
            ])
            ->assertSessionHasErrors('blocked_assessment');

        $this->actingAs($instructor)
            ->from(route('feedback.surveys.create'))
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Targeted block',
                'is_mandatory' => '1',
                'is_anonymous' => '1',
                'blocked_assessment' => 'exam:'.$exam->exam_id,
            ])
            ->assertSessionHasNoErrors();

        $survey = FeedbackSurvey::query()->where('title', 'Targeted block')->firstOrFail();
        $this->assertTrue($survey->blocksExam($exam));
        $this->assertTrue($survey->isAnonymous());
    }

    public function test_student_sees_anonymity_and_blocked_assessment_badges(): void
    {
        [$instructor, $student, $course, $module] = $this->staffAndStudent();
        $exam = $this->makeExam($course, $module, 'Final exam');

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Named blocking survey',
                'is_mandatory' => '1',
                'is_anonymous' => '0',
                'blocked_assessment' => 'exam:'.$exam->exam_id,
            ])
            ->assertSessionHasNoErrors();

        $survey = FeedbackSurvey::query()->where('title', 'Named blocking survey')->firstOrFail();
        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Comments',
            'order_index' => 1,
            'is_required' => true,
        ]);
        $survey->update([
            'status' => FeedbackSurvey::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('feedback.index'))
            ->assertOk()
            ->assertSee(__('pages.feedback_badge_identified'), false)
            ->assertSee(__('pages.feedback_badge_blocks_exam', ['name' => 'Final exam']), false);

        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk()
            ->assertSee(__('pages.feedback_badge_identified'), false)
            ->assertSee(__('pages.feedback_badge_blocks_exam', ['name' => 'Final exam']), false)
            ->assertSee(__('pages.feedback_identified_notice'), false)
            ->assertDontSee(__('pages.feedback_anonymous_notice'), false);
    }

    public function test_survey_blocks_only_the_selected_exam(): void
    {
        [$instructor, $student, $course, $module] = $this->staffAndStudent();
        $blocked = $this->gradedExam($course, $module, $student, 'Blocked exam', 80);
        $other = $this->gradedExam($course, $module, $student, 'Open exam', 70);

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Blocks one exam',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'is_anonymous' => true,
            'opened_at' => now(),
            'blocks_exam_id' => $blocked['exam']->exam_id,
        ]);

        $blocked['exam']->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $instructor->user_id,
        ]);
        $other['exam']->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $instructor->user_id,
        ]);

        $visibility = app(ExamResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $blocked['exam']->fresh()));
        $this->assertSame('pending_feedback', $visibility->hideReason($student, $blocked['exam']->fresh()));
        $this->assertTrue($visibility->canStudentViewScore($student, $other['exam']->fresh()));

        FeedbackSubmission::create([
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        $this->assertTrue($visibility->canStudentViewScore($student, $blocked['exam']->fresh()));
    }

    public function test_named_survey_report_shows_the_student_name(): void
    {
        [$instructor, $student, $course, $module] = $this->staffAndStudent([
            'first_name' => 'Visible',
            'second_name' => 'Student',
        ]);

        $survey = FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Named report survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'is_anonymous' => false,
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

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.report', $survey))
            ->assertOk()
            ->assertSee($student->displayName(), false)
            ->assertSee(__('pages.feedback_badge_identified'), false)
            ->assertDontSee(__('pages.feedback_request_identity'), false);

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.report.submission', [$survey, $submission]))
            ->assertOk()
            ->assertSee($student->displayName(), false);

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.report.reveal', [$survey, $submission]), [
                'reason' => 'Should not be needed for a named survey.',
            ])
            ->assertSessionHasErrors('reveal');

        $this->assertNotNull($question->question_id);
    }

    public function test_survey_blocks_only_the_selected_project_assessment(): void
    {
        Mail::fake();
        [$instructor, $student, $course, $module] = $this->staffAndStudent();

        $blocked = $this->gradedProject($instructor, $student, $course, $module, 'Blocked project');
        $other = $this->gradedProject($instructor, $student, $course, $module, 'Open project');

        FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Blocks one project',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'is_anonymous' => true,
            'opened_at' => now(),
            'blocks_project_assessment_id' => $blocked->project_assessment_id,
        ]);

        $blocked->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $instructor->user_id,
        ]);
        $other->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $instructor->user_id,
        ]);

        $visibility = app(ProjectResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $blocked->fresh()));
        $this->assertTrue($visibility->canStudentViewScore($student, $other->fresh()));
    }

    /**
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function staffCourse(): array
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'survey-block-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Survey Block Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $module = Module::create(['title' => 'Survey block module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        return [$instructor, $course, $module];
    }

    /**
     * @param  array<string, mixed>  $studentAttrs
     * @return array{0: User, 1: User, 2: Course, 3: Module}
     */
    private function staffAndStudent(array $studentAttrs = []): array
    {
        [$instructor, $course, $module] = $this->staffCourse();
        $studentRole = $this->createRole('student');
        $student = $this->createUser(array_merge([
            'email' => 'survey-block-student@example.com',
            'first_name' => 'Survey',
            'second_name' => 'Student',
        ], $studentAttrs));
        $this->assignCourseRole($student, $course, $studentRole);

        return [$instructor, $student, $course, $module];
    }

    private function makeExam(Course $course, Module $module, string $name): Exam
    {
        return Exam::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'exam_name' => $name,
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => 30,
            'total_points' => 10,
            'passing_score' => 50,
            'is_published' => true,
        ]);
    }

    /**
     * @return array{exam: Exam, result: ExamResult}
     */
    private function gradedExam(Course $course, Module $module, User $student, string $name, float $score): array
    {
        $exam = $this->makeExam($course, $module, $name);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->exam_id,
            'scheduled_date' => now()->subDay(),
            'is_completed' => true,
        ]);
        $result = ExamResult::create([
            'exam_id' => $exam->exam_id,
            'schedule_id' => $schedule->schedule_id,
            'user_id' => $student->user_id,
            'score' => $score,
            'status' => ExamResult::STATUS_GRADED,
            'submitted_at' => now()->subHour(),
        ]);

        return ['exam' => $exam, 'result' => $result];
    }

    private function gradedProject(User $admin, User $student, Course $course, Module $module, string $title)
    {
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => $title,
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'criteria' => [
                ['title' => 'Content', 'max_points' => 60],
                ['title' => 'Presentation', 'max_points' => 40],
            ],
        ], $admin);
        $assessment->update(['is_published' => true]);
        $assessment->load(['criteria', 'projects']);

        app(ProjectAssignmentService::class)->assignStudent($assessment, $student, notify: false);
        $project = $assessment->projects()->firstOrFail();
        app(ProjectGradingService::class)->gradeTeam(
            $assessment,
            $project->fresh(),
            [
                $assessment->criteria[0]->project_grade_criterion_id => 48,
                $assessment->criteria[1]->project_grade_criterion_id => 32,
            ],
            $admin
        );

        $this->assertNotNull(ProjectMemberGrade::query()->where('user_id', $student->user_id)->where('project_assessment_id', $assessment->project_assessment_id)->first());

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        return $assessment->fresh();
    }
}
