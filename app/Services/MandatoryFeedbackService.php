<?php

namespace App\Services;

use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\User;
use Illuminate\Support\Collection;

class MandatoryFeedbackService
{
    public function pendingForUser(User $user): Collection
    {
        if (! $user->isStudent()) {
            return collect();
        }

        $courseIds = $user->courses()->pluck('course.course_id');

        if ($courseIds->isEmpty()) {
            return collect();
        }

        // Course-wide blocking surveys (no module) may still soft-lock the portal.
        // Module surveys never do — they only hide that module's exam/project scores.
        $surveys = FeedbackSurvey::query()
            ->with(['course', 'module'])
            ->whereIn('course_id', $courseIds)
            ->whereNull('module_id')
            ->where('status', FeedbackSurvey::STATUS_OPEN)
            ->where('is_mandatory', true)
            ->where(function ($q) {
                $q->whereNull('due_at')->orWhere('due_at', '>', now());
            })
            ->orderBy('due_at')
            ->orderBy('survey_id')
            ->get();

        $submittedIds = $this->submittedSurveyIds($user, $surveys->pluck('survey_id'));

        return $surveys
            ->reject(fn (FeedbackSurvey $survey) => $submittedIds->contains((int) $survey->survey_id))
            ->map(fn (FeedbackSurvey $survey) => [
                'survey_id' => $survey->survey_id,
                'course_id' => $survey->course_id,
                'module_id' => $survey->module_id,
                'title' => $survey->title,
                'course_title' => $survey->course?->title,
                'module_name' => $survey->module?->title,
            ])
            ->values();
    }

    public function hasPending(User $user): bool
    {
        return $this->pendingForUser($user)->isNotEmpty();
    }

    public function firstPending(User $user): ?array
    {
        return $this->pendingForUser($user)->first();
    }

    /**
     * Open, not-past-due blocking surveys for a course/module that this user
     * has not submitted. Other modules are ignored. Closed leftovers never block scores.
     *
     * @return Collection<int, FeedbackSurvey>
     */
    public function unsubmittedMandatorySurveys(User $user, int $courseId, ?int $moduleId = null): Collection
    {
        return $this->unsubmittedBlockingSurveys($user, $courseId, $moduleId);
    }

    /**
     * Blocking surveys that hide this exam or project until the student submits.
     * Legacy rows with no target still hide every assessment on the module.
     *
     * @param  'exam'|'project'|null  $kind
     * @return Collection<int, FeedbackSurvey>
     */
    public function unsubmittedBlockingSurveys(
        User $user,
        int $courseId,
        ?int $moduleId = null,
        ?string $kind = null,
        ?int $assessmentId = null,
    ): Collection {
        $surveys = FeedbackSurvey::query()
            ->with(['blockedExam', 'blockedProjectAssessment'])
            ->where('course_id', $courseId)
            ->when(
                $moduleId,
                fn ($q) => $q->where('module_id', $moduleId),
                fn ($q) => $q->whereNull('module_id')
            )
            ->where('status', FeedbackSurvey::STATUS_OPEN)
            ->where('is_mandatory', true)
            ->where(function ($q) {
                $q->whereNull('due_at')->orWhere('due_at', '>', now());
            })
            ->when($kind && $assessmentId, function ($q) use ($kind, $assessmentId) {
                $q->where(function ($inner) use ($kind, $assessmentId) {
                    $inner->where(function ($legacy) {
                        $legacy->whereNull('blocks_exam_id')
                            ->whereNull('blocks_project_assessment_id');
                    });
                    if ($kind === FeedbackSurvey::BLOCK_KIND_EXAM) {
                        $inner->orWhere('blocks_exam_id', $assessmentId);
                    }
                    if ($kind === FeedbackSurvey::BLOCK_KIND_PROJECT) {
                        $inner->orWhere('blocks_project_assessment_id', $assessmentId);
                    }
                });
            })
            ->orderBy('survey_id')
            ->get();

        $submittedIds = $this->submittedSurveyIds($user, $surveys->pluck('survey_id'));

        return $surveys
            ->reject(fn (FeedbackSurvey $survey) => $submittedIds->contains((int) $survey->survey_id))
            ->values();
    }

    /**
     * @param  iterable<int|string>|Collection<int, mixed>  $surveyIds
     * @return Collection<int, int>
     */
    public function submittedSurveyIds(User $user, iterable $surveyIds): Collection
    {
        $ids = collect($surveyIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return FeedbackSubmission::query()
            ->where('user_id', $user->user_id)
            ->whereIn('survey_id', $ids->all())
            ->pluck('survey_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
