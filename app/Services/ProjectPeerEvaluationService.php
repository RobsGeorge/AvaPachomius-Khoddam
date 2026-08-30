<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectPeerRating;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anonymous, informational cross-team peer evaluation.
 * Students rate other teams (self-pick); never writes project_member_grades.
 */
class ProjectPeerEvaluationService
{
    public function isOpen(ProjectAssessment $assessment): bool
    {
        if (! $assessment->peer_eval_enabled) {
            return false;
        }

        $now = now();
        if ($assessment->peer_eval_opens_at && $assessment->peer_eval_opens_at->isFuture()) {
            return false;
        }
        if ($assessment->peer_eval_closes_at && $assessment->peer_eval_closes_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Closed | scheduled | open | ended
     */
    public function status(ProjectAssessment $assessment): string
    {
        if (! $assessment->peer_eval_enabled) {
            return 'closed';
        }
        if ($assessment->peer_eval_opens_at && $assessment->peer_eval_opens_at->isFuture()) {
            return 'scheduled';
        }
        if ($assessment->peer_eval_closes_at && $assessment->peer_eval_closes_at->isPast()) {
            return 'ended';
        }

        return 'open';
    }

    public function minPicks(ProjectAssessment $assessment): int
    {
        return max(1, (int) ($assessment->peer_eval_min_picks ?: 1));
    }

    public function maxPicks(ProjectAssessment $assessment): int
    {
        return max($this->minPicks($assessment), (int) ($assessment->peer_eval_max_picks ?: 3));
    }

    /**
     * Other non-cancelled teams on the assessment that have at least one submission.
     *
     * @return Collection<int, Project>
     */
    public function eligibleRateeTeams(ProjectAssessment $assessment, User $rater): Collection
    {
        $membership = $assessment->activeMembershipFor((int) $rater->user_id);
        $ownProjectId = $membership ? (int) $membership->project_id : 0;

        return $assessment->projects()
            ->whereNull('cancelled_at')
            ->where('project_id', '!=', $ownProjectId)
            ->whereHas('deliverableSubmissions')
            ->orderBy('sort_order')
            ->orderBy('project_id')
            ->get();
    }

    /**
     * Whether the viewer may open the read-only peer-review page for $target.
     */
    public function canPeerReview(Project $target, User $viewer): bool
    {
        $assessment = $target->assessment;
        if (! $assessment || ! $this->isOpen($assessment) || $target->isCancelled()) {
            return false;
        }

        $membership = $assessment->activeMembershipFor((int) $viewer->user_id);
        if (! $membership || (int) $membership->project_id === (int) $target->project_id) {
            return false;
        }

        return $target->deliverableSubmissions()->exists();
    }

    /**
     * Ratings already saved by this rater (keyed by ratee_project_id).
     *
     * @return Collection<int, ProjectPeerRating>
     */
    public function ratingsByRater(ProjectAssessment $assessment, User $rater): Collection
    {
        return ProjectPeerRating::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('rater_user_id', $rater->user_id)
            ->whereNotNull('ratee_project_id')
            ->get()
            ->keyBy(fn (ProjectPeerRating $row) => (int) $row->ratee_project_id);
    }

    /**
     * @return array{rated:int, min:int, max:int, complete:bool, eligible:int}
     */
    public function progress(ProjectAssessment $assessment, User $rater): array
    {
        $eligible = $this->eligibleRateeTeams($assessment, $rater)->count();
        $min = min($this->minPicks($assessment), max(1, $eligible));
        $max = min($this->maxPicks($assessment), max($min, $eligible));
        $rated = $this->ratingsByRater($assessment, $rater)->count();

        return [
            'rated' => $rated,
            'min' => $min,
            'max' => $max,
            'complete' => $rated >= $min,
            'eligible' => $eligible,
        ];
    }

    /**
     * Progressive save: upsert the given team ratings. Total distinct ratees for this
     * rater must stay ≤ max. Min is advisory until the student has enough picks.
     *
     * @param  list<array{ratee_project_id:int|string, score:int|string, comment?:?string}>  $ratings
     * @return Collection<int, ProjectPeerRating>
     */
    public function submitTeamRatings(ProjectAssessment $assessment, User $rater, array $ratings): Collection
    {
        if (! $this->isOpen($assessment)) {
            throw ValidationException::withMessages([
                'peer' => [__('projects.peer_eval_closed')],
            ]);
        }

        $membership = $assessment->activeMembershipFor((int) $rater->user_id);
        if (! $membership) {
            throw ValidationException::withMessages([
                'peer' => [__('projects.not_assigned')],
            ]);
        }

        $raterProjectId = (int) $membership->project_id;
        $eligibleIds = $this->eligibleRateeTeams($assessment, $rater)
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $progress = $this->progress($assessment, $rater);
        $max = $progress['max'];
        $scaleMax = max(1, (int) ($assessment->peer_eval_scale_max ?: 5));
        $existing = $this->ratingsByRater($assessment, $rater);

        $normalized = [];
        foreach ($ratings as $row) {
            $rateeId = (int) ($row['ratee_project_id'] ?? 0);
            if ($rateeId === $raterProjectId) {
                throw ValidationException::withMessages([
                    'ratings' => [__('projects.peer_eval_cannot_rate_own_team')],
                ]);
            }
            if (! in_array($rateeId, $eligibleIds, true)) {
                throw ValidationException::withMessages([
                    'ratings' => [__('projects.peer_eval_team_not_eligible')],
                ]);
            }

            $score = (int) ($row['score'] ?? 0);
            if ($score < 1 || $score > $scaleMax) {
                throw ValidationException::withMessages([
                    'ratings' => [__('projects.peer_eval_score_range', ['max' => $scaleMax])],
                ]);
            }

            $comment = isset($row['comment']) ? trim((string) $row['comment']) : null;
            $normalized[$rateeId] = [
                'ratee_project_id' => $rateeId,
                'score' => $score,
                'comment' => $comment === '' ? null : $comment,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'ratings' => [__('projects.peer_eval_ratings_required')],
            ]);
        }

        $wouldRate = $existing->keys()->merge(array_keys($normalized))->unique()->count();
        if ($wouldRate > $max) {
            throw ValidationException::withMessages([
                'ratings' => [__('projects.peer_eval_too_many_picks', ['max' => $max])],
            ]);
        }

        return DB::transaction(function () use ($assessment, $rater, $raterProjectId, $normalized) {
            $memberGradeCountBefore = ProjectMemberGrade::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->count();

            $saved = collect();
            foreach ($normalized as $row) {
                $saved->push(ProjectPeerRating::updateOrCreate(
                    [
                        'project_assessment_id' => $assessment->project_assessment_id,
                        'rater_user_id' => $rater->user_id,
                        'ratee_project_id' => $row['ratee_project_id'],
                    ],
                    [
                        'project_id' => $raterProjectId,
                        // Legacy NOT NULL column; unused for cross-team ratings.
                        'ratee_user_id' => 0,
                        'score' => $row['score'],
                        'comment' => $row['comment'],
                    ]
                ));
            }

            $memberGradeCountAfter = ProjectMemberGrade::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->count();

            if ($memberGradeCountAfter !== $memberGradeCountBefore) {
                throw ValidationException::withMessages([
                    'ratings' => [__('projects.peer_eval_informational_only')],
                ]);
            }

            AuditLogService::recordEvent('project.peer_ratings_submitted', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $raterProjectId,
                'rater_user_id' => $rater->user_id,
                'count' => $saved->count(),
            ]);

            return $saved;
        });
    }

    /**
     * Admin matrix: per ratee-team overall average + anonymous averages by rater team.
     * Never exposes rater user ids.
     *
     * @return list<array{
     *   project_id:int,
     *   title:string,
     *   overall_avg:?float,
     *   ratings_count:int,
     *   by_rater_team:list<array{project_id:int, title:string, average:float, ratings_count:int}>
     * }>
     */
    public function adminTeamAverages(ProjectAssessment $assessment): array
    {
        $teams = $assessment->projects()
            ->whereNull('cancelled_at')
            ->orderBy('sort_order')
            ->orderBy('project_id')
            ->get()
            ->keyBy('project_id');

        $ratings = ProjectPeerRating::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->whereNotNull('ratee_project_id')
            ->get();

        $rows = [];
        foreach ($teams as $projectId => $team) {
            $forTeam = $ratings->where('ratee_project_id', (int) $projectId);
            $byRaterTeam = [];
            foreach ($forTeam->groupBy('project_id') as $raterProjectId => $group) {
                $raterTeam = $teams->get((int) $raterProjectId);
                $byRaterTeam[] = [
                    'project_id' => (int) $raterProjectId,
                    'title' => $raterTeam?->title ?? '#'.$raterProjectId,
                    'average' => round((float) $group->avg('score'), 2),
                    'ratings_count' => $group->count(),
                ];
            }

            $rows[] = [
                'project_id' => (int) $projectId,
                'title' => $team->title,
                'overall_avg' => $forTeam->isEmpty()
                    ? null
                    : round((float) $forTeam->avg('score'), 2),
                'ratings_count' => $forTeam->count(),
                'by_rater_team' => $byRaterTeam,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{
     *     peer_eval_enabled?:mixed,
     *     peer_eval_opens_at?:?string,
     *     peer_eval_closes_at?:?string,
     *     peer_eval_scale_max?:int|string|null,
     *     peer_eval_prompt?:?string,
     *     peer_eval_min_picks?:int|string|null,
     *     peer_eval_max_picks?:int|string|null
     * }  $data
     */
    public function updateSettings(ProjectAssessment $assessment, array $data, User $actor): ProjectAssessment
    {
        $min = max(1, min(50, (int) ($data['peer_eval_min_picks'] ?? $assessment->peer_eval_min_picks ?: 1)));
        $max = max($min, min(50, (int) ($data['peer_eval_max_picks'] ?? $assessment->peer_eval_max_picks ?: 3)));

        $payload = [
            'peer_eval_enabled' => (bool) ($data['peer_eval_enabled'] ?? false),
            'peer_eval_opens_at' => $data['peer_eval_opens_at'] ?? null,
            'peer_eval_closes_at' => $data['peer_eval_closes_at'] ?? null,
            'peer_eval_scale_max' => max(1, min(10, (int) ($data['peer_eval_scale_max'] ?? 5))),
            'peer_eval_prompt' => array_key_exists('peer_eval_prompt', $data)
                ? (trim((string) ($data['peer_eval_prompt'] ?? '')) ?: null)
                : $assessment->peer_eval_prompt,
            'peer_eval_min_picks' => $min,
            'peer_eval_max_picks' => $max,
        ];

        $assessment->update($payload);

        AuditLogService::recordEvent('project.peer_eval_settings_updated', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'actor_user_id' => $actor->user_id,
            'enabled' => $payload['peer_eval_enabled'],
        ]);

        return $assessment->fresh();
    }

    public function openNow(ProjectAssessment $assessment, User $actor): ProjectAssessment
    {
        $opensAt = $assessment->peer_eval_opens_at;
        if (! $opensAt || $opensAt->isFuture()) {
            $opensAt = now();
        }

        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => $opensAt,
            'peer_eval_min_picks' => $this->minPicks($assessment),
            'peer_eval_max_picks' => $this->maxPicks($assessment),
            'peer_eval_scale_max' => max(1, (int) ($assessment->peer_eval_scale_max ?: 5)),
        ]);

        AuditLogService::recordEvent('project.peer_eval_opened', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $assessment->fresh();
    }

    public function closeNow(ProjectAssessment $assessment, User $actor): ProjectAssessment
    {
        $assessment->update([
            'peer_eval_closes_at' => now(),
        ]);

        AuditLogService::recordEvent('project.peer_eval_closed', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $assessment->fresh();
    }
}
