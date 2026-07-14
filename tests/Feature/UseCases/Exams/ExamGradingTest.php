<?php

namespace Tests\Feature\UseCases\Exams;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use App\Models\ExamSchedule;
use App\Models\User;
use App\Services\ExamGradingService;
use Tests\Support\EventModuleTestCase;

/**
 * Exam grading (UC-EXAM-04/05/06, TC-EXAM-*). Covers objective auto-grading, the
 * pending→manual essay path, and idempotent submission — the exam module's core
 * scoring logic, previously untested. Essay grading falls back to local keyword
 * matching in the test env (no OpenAI key), so no external calls are made.
 */
class ExamGradingTest extends EventModuleTestCase
{
    public function test_objective_answers_are_auto_graded_on_submit(): void
    {
        [$exam, $q1, $q2] = $this->objectiveExam();
        $student = $this->createUser(['email' => 'exam-obj@example.com']);

        $attempt = $this->newAttempt($exam, $student, [
            (string) $q1->question_id => $this->correctOptionId($q1),   // correct → 5
            (string) $q2->question_id => $this->wrongOptionId($q2),     // wrong   → 0
        ]);

        $result = app(ExamGradingService::class)->submitAttempt($attempt);

        $this->assertEquals(5.0, (float) $result->auto_score);
        $this->assertEquals(50.0, (float) $result->score);           // 5 / 10 * 100
        $this->assertSame('graded', $result->status);                // no essay pending
        $this->assertSame(ExamAttempt::STATUS_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_gradeObjective_scores_correct_wrong_and_unanswered(): void
    {
        [, $q1] = $this->objectiveExam();
        $service = app(ExamGradingService::class);

        // gradeObjective only reads selected_option_id + queries options, so the
        // answers need not be persisted (avoids the (attempt,question) unique key).
        $correct = new ExamAnswer(['selected_option_id' => $this->correctOptionId($q1)]);
        $wrong = new ExamAnswer(['selected_option_id' => $this->wrongOptionId($q1)]);
        $blank = new ExamAnswer(['selected_option_id' => null]);

        $this->assertEquals(5.0, $service->gradeObjective($correct, $q1));
        $this->assertEquals(0.0, $service->gradeObjective($wrong, $q1));
        $this->assertEquals(0.0, $service->gradeObjective($blank, $q1));
    }

    public function test_manual_essay_scoring_finalizes_the_result(): void
    {
        $exam = $this->makeExam(['exam_name' => 'Mixed']);
        $mcq = $this->mcq($exam, 5);
        $essay = ExamQuestion::create([
            'exam_id' => $exam->exam_id, 'question_type' => ExamQuestion::TYPE_ESSAY,
            'prompt' => 'Explain', 'points' => 5, 'order_index' => 1,
        ]);
        $student = $this->createUser(['email' => 'exam-essay@example.com']);

        $attempt = $this->newAttempt($exam, $student, [
            (string) $mcq->question_id => $this->correctOptionId($mcq),
            (string) $essay->question_id => ['text' => 'A thoughtful answer.'],
        ]);

        $grading = app(ExamGradingService::class);
        $result = $grading->submitAttempt($attempt);

        // Instructor awards full marks for the essay.
        $final = $grading->updateManualScores($result, [$essay->question_id => 5.0]);

        $this->assertSame('graded', $final->status);
        $this->assertEquals(10.0, (float) $final->manual_score); // mcq auto (5) + essay manual (5)
        $this->assertEquals(100.0, (float) $final->score);
    }

    public function test_resubmitting_an_attempt_returns_the_existing_result(): void
    {
        [$exam, $q1] = $this->objectiveExam();
        $student = $this->createUser(['email' => 'exam-idem@example.com']);
        $attempt = $this->newAttempt($exam, $student, [(string) $q1->question_id => $this->correctOptionId($q1)]);
        $service = app(ExamGradingService::class);

        $first = $service->submitAttempt($attempt);
        $second = $service->submitAttempt($attempt->fresh());

        $this->assertSame($first->result_id, $second->result_id);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $overrides */
    private function makeExam(array $overrides = []): Exam
    {
        $course = $this->createCourse();

        return Exam::create(array_merge([
            'course_id' => $course->course_id, 'exam_name' => 'Objective', 'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE, 'duration_minutes' => 30, 'scheduled_date' => now()->addDay(),
            'total_points' => 10, 'passing_score' => 5, 'is_published' => true,
        ], $overrides));
    }

    /** @return array{0: Exam, 1: ExamQuestion, 2: ExamQuestion} */
    private function objectiveExam(): array
    {
        $exam = $this->makeExam();

        return [$exam, $this->mcq($exam, 5), $this->mcq($exam, 5)];
    }

    /** @param array<string,mixed> $answersJson */
    private function newAttempt(Exam $exam, User $student, array $answersJson = []): ExamAttempt
    {
        $schedule = ExamSchedule::create(['exam_id' => $exam->exam_id, 'scheduled_date' => now()->addDay()]);

        return ExamAttempt::create([
            'exam_id' => $exam->exam_id,
            'schedule_id' => $schedule->schedule_id,
            'user_id' => $student->user_id,
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'answers_json' => $answersJson ?: null,
        ]);
    }

    private function mcq(Exam $exam, float $points): ExamQuestion
    {
        $q = ExamQuestion::create([
            'exam_id' => $exam->exam_id, 'question_type' => ExamQuestion::TYPE_MCQ,
            'prompt' => 'Pick one', 'points' => $points, 'order_index' => 0,
        ]);
        ExamQuestionOption::create(['question_id' => $q->question_id, 'label' => 'Right', 'is_correct' => true, 'order_index' => 0]);
        ExamQuestionOption::create(['question_id' => $q->question_id, 'label' => 'Wrong', 'is_correct' => false, 'order_index' => 1]);

        return $q;
    }

    private function correctOptionId(ExamQuestion $q): int
    {
        return (int) ExamQuestionOption::where('question_id', $q->question_id)->where('is_correct', true)->value('option_id');
    }

    private function wrongOptionId(ExamQuestion $q): int
    {
        return (int) ExamQuestionOption::where('question_id', $q->question_id)->where('is_correct', false)->value('option_id');
    }
}
