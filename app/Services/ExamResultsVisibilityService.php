<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\User;

class ExamResultsVisibilityService
{
    public function areResultsAnnounced(Exam $exam): bool
    {
        return $exam->results_announced_at !== null;
    }

    /**
     * Student may see a numeric score only when results are announced and any
     * mandatory module survey (open or closed) has been submitted.
     */
    public function canStudentViewScore(User $user, Exam $exam): bool
    {
        if (! $this->areResultsAnnounced($exam)) {
            return false;
        }

        return $this->hasCompletedRequiredModuleSurveys($user, $exam);
    }

    public function hasCompletedRequiredModuleSurveys(User $user, Exam $exam): bool
    {
        $surveys = $this->mandatorySurveysForExam($exam);

        if ($surveys->isEmpty()) {
            return true;
        }

        $submittedIds = FeedbackSubmission::query()
            ->where('user_id', $user->user_id)
            ->whereIn('survey_id', $surveys->pluck('survey_id'))
            ->pluck('survey_id');

        return $surveys->every(
            fn (FeedbackSurvey $survey) => $submittedIds->contains($survey->survey_id)
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, FeedbackSurvey>
     */
    public function mandatorySurveysForExam(Exam $exam)
    {
        if (! $exam->course_id) {
            return collect();
        }

        return FeedbackSurvey::query()
            ->where('course_id', $exam->course_id)
            ->when(
                $exam->module_id,
                fn ($q) => $q->where('module_id', $exam->module_id),
                fn ($q) => $q->whereNull('module_id')
            )
            ->where('is_mandatory', true)
            ->whereIn('status', [
                FeedbackSurvey::STATUS_OPEN,
                FeedbackSurvey::STATUS_CLOSED,
            ])
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

        if (! $this->hasCompletedRequiredModuleSurveys($user, $exam)) {
            return 'pending_feedback';
        }

        return 'visible';
    }
}
