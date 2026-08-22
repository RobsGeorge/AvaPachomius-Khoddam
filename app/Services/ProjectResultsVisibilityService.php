<?php

namespace App\Services;

use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\ProjectAssessment;
use App\Models\User;

class ProjectResultsVisibilityService
{
    public function areResultsAnnounced(ProjectAssessment $assessment): bool
    {
        return $assessment->results_announced_at !== null;
    }

    /**
     * Student may see a numeric score only when results are announced and any
     * mandatory module survey (open or closed) has been submitted.
     */
    public function canStudentViewScore(User $user, ProjectAssessment $assessment): bool
    {
        if (! $this->areResultsAnnounced($assessment)) {
            return false;
        }

        return $this->hasCompletedRequiredModuleSurveys($user, $assessment);
    }

    public function hasCompletedRequiredModuleSurveys(User $user, ProjectAssessment $assessment): bool
    {
        $surveys = $this->mandatorySurveysForAssessment($assessment);

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
    public function mandatorySurveysForAssessment(ProjectAssessment $assessment)
    {
        if (! $assessment->course_id) {
            return collect();
        }

        return FeedbackSurvey::query()
            ->where('course_id', $assessment->course_id)
            ->when(
                $assessment->module_id,
                fn ($q) => $q->where('module_id', $assessment->module_id),
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
    public function hideReason(User $user, ProjectAssessment $assessment): string
    {
        if (! $this->areResultsAnnounced($assessment)) {
            return 'pending_announcement';
        }

        if (! $this->hasCompletedRequiredModuleSurveys($user, $assessment)) {
            return 'pending_feedback';
        }

        return 'visible';
    }
}
