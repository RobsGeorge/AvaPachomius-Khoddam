<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectPeerRating;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anonymous, informational peer evaluation. Never writes project_member_grades.
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
     * Teammates the rater still needs to score (excludes self).
     *
     * @return Collection<int, User>
     */
    public function pendingTeammates(Project $project, User $rater): Collection
    {
        $ratedIds = ProjectPeerRating::query()
            ->where('project_id', $project->project_id)
            ->where('rater_user_id', $rater->user_id)
            ->pluck('ratee_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $project->activeMembers()
            ->filter(fn (User $member) => (int) $member->user_id !== (int) $rater->user_id)
            ->reject(fn (User $member) => in_array((int) $member->user_id, $ratedIds, true))
            ->values();
    }

    /**
     * @param  list<array{ratee_user_id:int|string, score:int|string, comment?:?string}>  $ratings
     * @return Collection<int, ProjectPeerRating>
     */
    public function submitRatings(Project $project, User $rater, array $ratings): Collection
    {
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        if (! $this->isOpen($assessment)) {
            throw ValidationException::withMessages([
                'peer' => [__('projects.peer_eval_closed')],
            ]);
        }

        $membership = $assessment->activeMembershipFor((int) $rater->user_id);
        if (! $membership || (int) $membership->project_id !== (int) $project->project_id) {
            throw ValidationException::withMessages([
                'peer' => [__('projects.not_assigned')],
            ]);
        }

        $teammateIds = $project->activeMemberships()
            ->where('user_id', '!=', $rater->user_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $scaleMax = max(1, (int) ($assessment->peer_eval_scale_max ?: 5));
        $saved = collect();

        return DB::transaction(function () use ($project, $assessment, $rater, $ratings, $teammateIds, $scaleMax, $saved) {
            $memberGradeCountBefore = ProjectMemberGrade::query()
                ->where('project_id', $project->project_id)
                ->count();

            foreach ($ratings as $row) {
                $rateeId = (int) ($row['ratee_user_id'] ?? 0);
                if ($rateeId === (int) $rater->user_id) {
                    throw ValidationException::withMessages([
                        'ratings' => [__('projects.peer_eval_cannot_rate_self')],
                    ]);
                }
                if (! in_array($rateeId, $teammateIds, true)) {
                    throw ValidationException::withMessages([
                        'ratings' => [__('projects.peer_eval_not_teammate')],
                    ]);
                }

                $score = (int) ($row['score'] ?? 0);
                if ($score < 1 || $score > $scaleMax) {
                    throw ValidationException::withMessages([
                        'ratings' => [__('projects.peer_eval_score_range', ['max' => $scaleMax])],
                    ]);
                }

                $comment = isset($row['comment']) ? trim((string) $row['comment']) : null;

                $saved->push(ProjectPeerRating::updateOrCreate(
                    [
                        'project_id' => $project->project_id,
                        'rater_user_id' => $rater->user_id,
                        'ratee_user_id' => $rateeId,
                    ],
                    [
                        'project_assessment_id' => $assessment->project_assessment_id,
                        'score' => $score,
                        'comment' => $comment === '' ? null : $comment,
                    ]
                ));
            }

            $memberGradeCountAfter = ProjectMemberGrade::query()
                ->where('project_id', $project->project_id)
                ->count();

            // Hard guard: peer scores must never create or mutate member grades.
            if ($memberGradeCountAfter !== $memberGradeCountBefore) {
                throw ValidationException::withMessages([
                    'ratings' => [__('projects.peer_eval_informational_only')],
                ]);
            }

            AuditLogService::recordEvent('project.peer_ratings_submitted', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'rater_user_id' => $rater->user_id,
                'count' => $saved->count(),
            ]);

            return $saved;
        });
    }

    /**
     * Admin matrix: ratee averages with anonymous rater counts. Never exposes rater ids.
     *
     * @return list<array{user_id:int, display_name:string, average:?float, ratings_count:int}>
     */
    public function adminAverages(Project $project): array
    {
        $ratings = ProjectPeerRating::query()
            ->where('project_id', $project->project_id)
            ->get()
            ->groupBy('ratee_user_id');

        $rows = [];
        foreach ($project->activeMemberships()->with('user')->get() as $membership) {
            $user = $membership->user;
            $group = $ratings->get($membership->user_id) ?? collect();
            $rows[] = [
                'user_id' => (int) $membership->user_id,
                'display_name' => $user?->displayName() ?? '#'.$membership->user_id,
                'average' => $group->isEmpty()
                    ? null
                    : round((float) $group->avg('score'), 2),
                'ratings_count' => $group->count(),
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
     *     peer_eval_prompt?:?string
     * }  $data
     */
    public function updateSettings(ProjectAssessment $assessment, array $data, User $actor): ProjectAssessment
    {
        $payload = [
            'peer_eval_enabled' => (bool) ($data['peer_eval_enabled'] ?? false),
            'peer_eval_opens_at' => $data['peer_eval_opens_at'] ?? null,
            'peer_eval_closes_at' => $data['peer_eval_closes_at'] ?? null,
            'peer_eval_scale_max' => max(1, min(10, (int) ($data['peer_eval_scale_max'] ?? 5))),
            'peer_eval_prompt' => isset($data['peer_eval_prompt'])
                ? (trim((string) $data['peer_eval_prompt']) ?: null)
                : $assessment->peer_eval_prompt,
        ];

        $assessment->update($payload);

        AuditLogService::recordEvent('project.peer_eval_settings_updated', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'actor_user_id' => $actor->user_id,
            'enabled' => $payload['peer_eval_enabled'],
        ]);

        return $assessment->fresh();
    }
}
