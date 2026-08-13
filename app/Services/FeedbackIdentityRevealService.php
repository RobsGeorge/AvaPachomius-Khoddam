<?php

namespace App\Services;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FeedbackIdentityRevealService
{
    public function requestReveal(
        User $requester,
        FeedbackSurvey $survey,
        FeedbackSubmission $submission,
        string $reason,
        ?FeedbackAnswer $answer = null
    ): FeedbackIdentityRevealRequest {
        abort_unless((int) $submission->survey_id === (int) $survey->survey_id, 404);

        if ($answer !== null) {
            abort_unless((int) $answer->submission_id === (int) $submission->submission_id, 404);
        }

        $existing = FeedbackIdentityRevealRequest::query()
            ->where('requested_by_user_id', $requester->user_id)
            ->where('submission_id', $submission->submission_id)
            ->where('status', FeedbackIdentityRevealRequest::STATUS_PENDING)
            ->when(
                $answer,
                fn ($q) => $q->where('answer_id', $answer->answer_id),
                fn ($q) => $q->whereNull('answer_id')
            )
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'reveal' => __('pages.feedback_reveal_already_pending'),
            ]);
        }

        if ($this->viewerCanSeeIdentity($requester, $submission)) {
            throw ValidationException::withMessages([
                'reveal' => __('pages.feedback_reveal_already_active'),
            ]);
        }

        $request = FeedbackIdentityRevealRequest::create([
            'church_id' => $survey->church_id ?? null,
            'survey_id' => $survey->survey_id,
            'submission_id' => $submission->submission_id,
            'answer_id' => $answer?->answer_id,
            'requested_by_user_id' => $requester->user_id,
            'reason' => $reason,
            'status' => FeedbackIdentityRevealRequest::STATUS_PENDING,
        ]);

        AuditLogService::recordEvent('feedback.identity_reveal.requested', [
            'actor_user_id' => $requester->user_id,
            'survey_id' => $survey->survey_id,
            'submission_id' => $submission->submission_id,
            'answer_id' => $answer?->answer_id,
            'reveal_request_id' => $request->reveal_request_id,
        ]);

        return $request;
    }

    public function approve(FeedbackIdentityRevealRequest $request, User $reviewer): FeedbackIdentityRevealRequest
    {
        abort_unless($request->isPending(), 409);

        $request->update([
            'status' => FeedbackIdentityRevealRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
            'expires_at' => now()->addDays(FeedbackIdentityRevealRequest::DEFAULT_REVEAL_DAYS),
        ]);

        AuditLogService::recordEvent('feedback.identity_reveal.approved', [
            'actor_user_id' => $reviewer->user_id,
            'requester_user_id' => $request->requested_by_user_id,
            'survey_id' => $request->survey_id,
            'submission_id' => $request->submission_id,
            'reveal_request_id' => $request->reveal_request_id,
            'expires_at' => $request->expires_at?->toIso8601String(),
        ]);

        return $request->fresh();
    }

    public function deny(FeedbackIdentityRevealRequest $request, User $reviewer): FeedbackIdentityRevealRequest
    {
        abort_unless($request->isPending(), 409);

        $request->update([
            'status' => FeedbackIdentityRevealRequest::STATUS_DENIED,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
            'expires_at' => null,
        ]);

        AuditLogService::recordEvent('feedback.identity_reveal.denied', [
            'actor_user_id' => $reviewer->user_id,
            'requester_user_id' => $request->requested_by_user_id,
            'survey_id' => $request->survey_id,
            'submission_id' => $request->submission_id,
            'reveal_request_id' => $request->reveal_request_id,
        ]);

        return $request->fresh();
    }

    public function viewerCanSeeIdentity(User $viewer, FeedbackSubmission $submission): bool
    {
        return FeedbackIdentityRevealRequest::query()
            ->where('submission_id', $submission->submission_id)
            ->where('requested_by_user_id', $viewer->user_id)
            ->where('status', FeedbackIdentityRevealRequest::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Map submission_id => active approved reveal for this viewer.
     *
     * @param  Collection<int, int|string>  $submissionIds
     * @return Collection<int, FeedbackIdentityRevealRequest>
     */
    public function activeRevealsForViewer(User $viewer, Collection $submissionIds): Collection
    {
        if ($submissionIds->isEmpty()) {
            return collect();
        }

        return FeedbackIdentityRevealRequest::query()
            ->where('requested_by_user_id', $viewer->user_id)
            ->whereIn('submission_id', $submissionIds)
            ->where('status', FeedbackIdentityRevealRequest::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('submission_id');
    }

    /**
     * Pending requests keyed by submission (and optional answer) for this viewer.
     *
     * @param  Collection<int, int|string>  $submissionIds
     * @return Collection<int, FeedbackIdentityRevealRequest>
     */
    public function pendingRequestsForViewer(User $viewer, Collection $submissionIds): Collection
    {
        if ($submissionIds->isEmpty()) {
            return collect();
        }

        return FeedbackIdentityRevealRequest::query()
            ->where('requested_by_user_id', $viewer->user_id)
            ->whereIn('submission_id', $submissionIds)
            ->where('status', FeedbackIdentityRevealRequest::STATUS_PENDING)
            ->get();
    }

    public function anonymousLabel(FeedbackSubmission $submission): string
    {
        return __('pages.feedback_anonymous_response', [
            'id' => $submission->submission_id,
        ]);
    }
}
