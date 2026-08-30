<?php

namespace App\Services;

use App\Models\FeedbackSurvey;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectResultsVisibilityService
{
    public function __construct(
        private MandatoryFeedbackService $mandatoryFeedback,
    ) {}

    public function areResultsAnnounced(ProjectAssessment $assessment): bool
    {
        return $assessment->results_announced_at !== null;
    }

    /**
     * Student may see a numeric score only when results are announced, they
     * have a recorded grade, and any currently required module survey has
     * been submitted.
     */
    public function canStudentViewScore(User $user, ProjectAssessment $assessment): bool
    {
        if (! $this->areResultsAnnounced($assessment)) {
            return false;
        }

        if (! $this->studentHasViewableResult($user, $assessment)) {
            return false;
        }

        return $this->hasCompletedRequiredModuleSurveys($user, $assessment);
    }

    public function hasCompletedRequiredModuleSurveys(User $user, ProjectAssessment $assessment): bool
    {
        return $this->mandatorySurveysForAssessment($assessment, $user)->isEmpty();
    }

    public function studentHasViewableResult(User $user, ProjectAssessment $assessment): bool
    {
        return ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $user->user_id)
            ->whereNotNull('percent')
            ->exists();
    }

    /**
     * @return Collection<int, FeedbackSurvey>
     */
    public function mandatorySurveysForAssessment(ProjectAssessment $assessment, ?User $user = null)
    {
        if (! $assessment->course_id) {
            return collect();
        }

        if ($user) {
            return $this->mandatoryFeedback->unsubmittedMandatorySurveys(
                $user,
                (int) $assessment->course_id,
                $assessment->module_id ? (int) $assessment->module_id : null,
            );
        }

        return FeedbackSurvey::query()
            ->where('course_id', $assessment->course_id)
            ->when(
                $assessment->module_id,
                fn ($q) => $q->where('module_id', $assessment->module_id),
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
    public function hideReason(User $user, ProjectAssessment $assessment): string
    {
        if (! $this->areResultsAnnounced($assessment)) {
            return 'pending_announcement';
        }

        if (! $this->studentHasViewableResult($user, $assessment)) {
            return 'pending_assessment';
        }

        if (! $this->hasCompletedRequiredModuleSurveys($user, $assessment)) {
            return 'pending_feedback';
        }

        return 'visible';
    }
}
