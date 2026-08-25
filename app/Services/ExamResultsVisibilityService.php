<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeedbackSurvey;
use App\Models\User;
use Illuminate\Support\Collection;

class ExamResultsVisibilityService
{
    public function __construct(
        private MandatoryFeedbackService $mandatoryFeedback,
    ) {}

    public function areResultsAnnounced(Exam $exam): bool
    {
        return $exam->results_announced_at !== null;
    }

    /**
     * Student may see a numeric score only when results are announced, they
     * have a graded (or cheater) result of their own, and any currently
     * open blocking survey for this exam's module has been submitted.
     */
    public function canStudentViewScore(User $user, Exam $exam): bool
    {
        if (! $this->areResultsAnnounced($exam)) {
            return false;
        }

        if (! $this->studentHasViewableResult($user, $exam)) {
            return false;
        }

        return $this->hasCompletedRequiredModuleSurveys($user, $exam);
    }

    public function hasCompletedRequiredModuleSurveys(User $user, Exam $exam): bool
    {
        return $this->mandatorySurveysForExam($exam, $user)->isEmpty();
    }

    public function studentHasViewableResult(User $user, Exam $exam): bool
    {
        return ExamResult::query()
            ->where('exam_id', $exam->exam_id)
            ->where('user_id', $user->user_id)
            ->where(function ($q) {
                $q->whereNotNull('score')
                    ->orWhere('status', ExamResult::STATUS_CHEATER);
            })
            ->exists();
    }

    /**
     * Open blocking surveys for this exam's module that the student has not submitted.
     * Surveys attached to other modules never appear here.
     *
     * @return Collection<int, FeedbackSurvey>
     */
    public function mandatorySurveysForExam(Exam $exam, ?User $user = null)
    {
        if (! $exam->course_id) {
            return collect();
        }

        if ($user) {
            return $this->mandatoryFeedback->unsubmittedMandatorySurveys(
                $user,
                (int) $exam->course_id,
                $exam->module_id ? (int) $exam->module_id : null,
            );
        }

        return FeedbackSurvey::query()
            ->where('course_id', $exam->course_id)
            ->when(
                $exam->module_id,
                fn ($q) => $q->where('module_id', $exam->module_id),
                fn ($q) => $q->whereNull('module_id')
            )
            ->where('is_mandatory', true)
            ->where('status', FeedbackSurvey::STATUS_OPEN)
            ->where(function ($q) {
                $q->whereNull('due_at')->orWhere('due_at', '>', now());
            })
            ->orderBy('survey_id')
            ->get();
    }

    /**
     * Machine reason for UI copy when the score is hidden.
     */
    public function hideReason(User $user, Exam $exam): string
    {
        if (! $this->areResultsAnnounced($exam)) {
            return 'pending_announcement';
        }

        if (! $this->studentHasViewableResult($user, $exam)) {
            return 'pending_assessment';
        }

        if (! $this->hasCompletedRequiredModuleSurveys($user, $exam)) {
            return 'pending_feedback';
        }

        return 'visible';
    }
}
