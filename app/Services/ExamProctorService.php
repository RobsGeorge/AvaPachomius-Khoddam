<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamProctorEvent;
use App\Models\ExamResult;
use Illuminate\Support\Facades\DB;

class ExamProctorService
{
    public function __construct(
        private ExamGradingService $grading,
    ) {}

    /**
     * @return array{action: string, message: string, warnings: int, redirect?: string}
     */
    public function recordViolation(ExamAttempt $attempt, string $eventType, ?string $details = null): array
    {
        if ($attempt->terminated_for_cheating || $attempt->isSubmitted()) {
            return [
                'action'   => 'terminated',
                'message'  => __('exams.proctor_already_terminated'),
                'warnings' => (int) $attempt->proctor_warnings,
                'redirect' => route('exams.attempt.confirmation', $attempt->schedule_id),
            ];
        }

        return DB::transaction(function () use ($attempt, $eventType, $details) {
            $attempt = ExamAttempt::lockForUpdate()->find($attempt->attempt_id);
            $maxWarnings = (int) config('exams.proctor_max_warnings', 1);

            if ($this->isDuplicateEvent($attempt, $eventType)) {
                return [
                    'action'   => 'ignored',
                    'message'  => '',
                    'warnings' => (int) $attempt->proctor_warnings,
                ];
            }

            $newWarningCount = (int) $attempt->proctor_warnings + 1;

            ExamProctorEvent::create([
                'attempt_id'     => $attempt->attempt_id,
                'exam_id'        => $attempt->exam_id,
                'schedule_id'    => $attempt->schedule_id,
                'user_id'        => $attempt->user_id,
                'event_type'     => $eventType,
                'warning_number' => $newWarningCount,
                'details'        => $details,
                'created_at'     => now(),
            ]);

            $attempt->update(['proctor_warnings' => $newWarningCount]);

            if ($newWarningCount > $maxWarnings) {
                return $this->terminateForCheating($attempt);
            }

            return [
                'action'   => 'warn',
                'message'  => __('exams.proctor_first_warning'),
                'warnings' => $newWarningCount,
            ];
        });
    }

    public function terminateForCheating(ExamAttempt $attempt): array
    {
        $attempt->update([
            'terminated_for_cheating' => true,
            'terminated_at'           => now(),
            'status'                  => ExamAttempt::STATUS_TERMINATED,
            'submitted_at'            => now(),
        ]);

        if ($attempt->answers_json) {
            $this->grading->persistAnswers($attempt, $attempt->answers_json);
        }

        ExamResult::updateOrCreate(
            [
                'exam_id'     => $attempt->exam_id,
                'user_id'     => $attempt->user_id,
                'schedule_id' => $attempt->schedule_id,
            ],
            [
                'attempt_id'   => $attempt->attempt_id,
                'score'        => 0,
                'auto_score'   => 0,
                'status'       => ExamResult::STATUS_CHEATER,
                'submitted_at' => now(),
            ]
        );

        return [
            'action'   => 'terminated',
            'message'  => __('exams.proctor_exam_terminated'),
            'warnings' => (int) $attempt->proctor_warnings,
            'redirect' => route('exams.attempt.confirmation', $attempt->schedule_id),
        ];
    }

    public function clearCheaterFlag(ExamResult $result, ?float $newScore = null): ExamResult
    {
        $result->loadMissing('attempt');

        $updates = [
            'status' => ExamResult::STATUS_GRADED,
        ];

        if ($newScore !== null) {
            $updates['score'] = $newScore;
            $updates['manual_score'] = $newScore;
        }

        $result->update($updates);

        if ($result->attempt) {
            $result->attempt->update([
                'terminated_for_cheating' => false,
                'status'                  => ExamAttempt::STATUS_GRADED,
            ]);
        }

        return $result->fresh();
    }

    private function isDuplicateEvent(ExamAttempt $attempt, string $eventType): bool
    {
        $debounceSeconds = (int) config('exams.proctor_debounce_seconds', 5);

        return ExamProctorEvent::where('attempt_id', $attempt->attempt_id)
            ->where('event_type', $eventType)
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->exists();
    }
}
