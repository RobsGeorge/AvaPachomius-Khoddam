<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use Illuminate\Support\Facades\DB;

class ExamGradingService
{
    public function __construct(
        private EssayGradingService $essayGrading,
    ) {}

    /** @param array<string, mixed> $answersJson keyed by question_id */
    public function persistAnswers(ExamAttempt $attempt, array $answersJson): void
    {
        $attempt->update(['answers_json' => $answersJson]);

        $attempt->loadMissing('exam.questions.options');

        foreach ($attempt->exam->questions as $question) {
            $payload = $answersJson[(string) $question->question_id] ?? $answersJson[$question->question_id] ?? null;
            if ($payload === null) {
                continue;
            }

            $data = [
                'selected_option_id' => null,
                'text_answer'        => null,
            ];

            if ($question->isAutoGradable()) {
                $optionId = is_array($payload) ? ($payload['option_id'] ?? null) : $payload;
                $data['selected_option_id'] = $optionId ? (int) $optionId : null;
            } else {
                $text = is_array($payload) ? ($payload['text'] ?? '') : (string) $payload;
                $data['text_answer'] = $text;
            }

            ExamAnswer::updateOrCreate(
                [
                    'attempt_id'  => $attempt->attempt_id,
                    'question_id' => $question->question_id,
                ],
                $data
            );
        }
    }

    public function submitAttempt(ExamAttempt $attempt): ExamResult
    {
        if ($attempt->isSubmitted()) {
            return $attempt->result ?? $this->buildResultRecord($attempt);
        }

        return DB::transaction(function () use ($attempt) {
            $attempt->loadMissing('exam.questions.options', 'schedule', 'answers.question');

            if ($attempt->answers_json) {
                $this->persistAnswers($attempt, $attempt->answers_json);
                $attempt->refresh(['answers']);
            }

            $autoPoints = 0.0;
            $essayPending = false;

            foreach ($attempt->answers as $answer) {
                $question = $answer->question;
                if (! $question) {
                    continue;
                }

                if ($question->isAutoGradable()) {
                    $score = $this->gradeObjective($answer, $question);
                    $answer->update([
                        'auto_score' => $score,
                        'graded_at'  => now(),
                    ]);
                    $autoPoints += $score;
                } elseif ($question->question_type === ExamQuestion::TYPE_ESSAY) {
                    $essayScore = $this->essayGrading->grade($answer, $question);
                    $answer->update([
                        'auto_score'  => $essayScore['score'],
                        'ai_feedback' => $essayScore['feedback'],
                        'graded_at'   => now(),
                    ]);
                    $autoPoints += $essayScore['score'];
                    if ($essayScore['needs_review']) {
                        $essayPending = true;
                    }
                }
            }

            $totalPoints = max(1, (float) $attempt->exam->total_points);
            $percent = round(($autoPoints / $totalPoints) * 100, 2);

            $attempt->update([
                'status'       => ExamAttempt::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $result = ExamResult::updateOrCreate(
                [
                    'exam_id'     => $attempt->exam_id,
                    'user_id'     => $attempt->user_id,
                    'schedule_id' => $attempt->schedule_id,
                ],
                [
                    'attempt_id'   => $attempt->attempt_id,
                    'auto_score'   => $autoPoints,
                    'score'        => $percent,
                    'status'       => $essayPending ? ExamResult::STATUS_SUBMITTED : ExamResult::STATUS_GRADED,
                    'submitted_at' => now(),
                ]
            );

            $schedule = $attempt->schedule;
            if ($schedule && $this->allStudentsSubmitted($schedule)) {
                $schedule->update(['is_completed' => true]);
            }

            return $result;
        });
    }

    public function gradeObjective(ExamAnswer $answer, ExamQuestion $question): float
    {
        if (! $answer->selected_option_id) {
            return 0.0;
        }

        $correct = ExamQuestionOption::where('question_id', $question->question_id)
            ->where('option_id', $answer->selected_option_id)
            ->where('is_correct', true)
            ->exists();

        return $correct ? (float) $question->points : 0.0;
    }

    public function updateManualScores(ExamResult $result, array $scoresByQuestion): ExamResult
    {
        $result->loadMissing('attempt.answers.question', 'exam');

        $manualPoints = 0.0;

        foreach ($result->attempt?->answers ?? [] as $answer) {
            $qid = $answer->question_id;
            if (array_key_exists($qid, $scoresByQuestion)) {
                $manual = (float) $scoresByQuestion[$qid];
                $answer->update(['manual_score' => $manual]);
                $manualPoints += $manual;

                continue;
            }

            $manualPoints += $answer->effectiveScore() ?? 0;
        }

        $totalPoints = max(1, (float) $result->exam->total_points);
        $percent = round(($manualPoints / $totalPoints) * 100, 2);

        $result->update([
            'manual_score' => $manualPoints,
            'score'        => $percent,
            'status'       => ExamResult::STATUS_GRADED,
        ]);

        return $result->fresh();
    }

    public function saveOfflineScore(ExamResult $result, float $score): ExamResult
    {
        $result->update([
            'score'        => $score,
            'manual_score' => $score,
            'status'       => ExamResult::STATUS_GRADED,
            'submitted_at' => $result->submitted_at ?? now(),
        ]);

        return $result;
    }

    private function buildResultRecord(ExamAttempt $attempt): ExamResult
    {
        return ExamResult::firstOrCreate(
            [
                'exam_id'     => $attempt->exam_id,
                'user_id'     => $attempt->user_id,
                'schedule_id' => $attempt->schedule_id,
            ],
            ['attempt_id' => $attempt->attempt_id]
        );
    }

    private function allStudentsSubmitted(ExamSchedule $schedule): bool
    {
        $attempts = $schedule->attempts();

        if (! $attempts->exists()) {
            return false;
        }

        return ! $attempts
            ->whereNotIn('status', [
                ExamAttempt::STATUS_SUBMITTED,
                ExamAttempt::STATUS_GRADED,
                ExamAttempt::STATUS_TERMINATED,
            ])
            ->exists();
    }
}
