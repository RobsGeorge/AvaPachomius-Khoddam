<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectTeamCriterionScore;
use App\Models\ProjectTeamGrade;
use App\Models\ProjectTeamGradeCriterion;
use App\Models\ProjectTeamGradeScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectGradingService
{
    public function maxPoints(ProjectAssessment $assessment): float
    {
        $criteria = $assessment->relationLoaded('criteria')
            ? $assessment->criteria
            : $assessment->criteria()->get();

        if ($criteria->isNotEmpty()) {
            return round((float) $criteria->sum('max_points'), 2);
        }

        return round((float) $assessment->max_points, 2);
    }

    public function percentFor(float $points, ProjectAssessment $assessment): float
    {
        $max = $this->maxPoints($assessment);
        if ($max <= 0) {
            return 0.0;
        }

        return round($points / $max * 100, 2);
    }

    /**
     * The rubric a single team is actually graded against: the shared criteria,
     * with per-team max/title overrides applied and excluded rows dropped,
     * followed by that team's extra criteria.
     *
     * @return list<array{key:string, kind:string, id:int, title:string, max_points:float}>
     */
    public function effectiveCriteria(ProjectAssessment $assessment, Project $project): array
    {
        $shared = $assessment->relationLoaded('criteria')
            ? $assessment->criteria
            : $assessment->criteria()->orderBy('sort_order')->get();

        $teamRows = ProjectTeamGradeCriterion::query()
            ->where('project_id', $project->project_id)
            ->orderBy('sort_order')
            ->get();

        $overrides = $teamRows
            ->filter(fn (ProjectTeamGradeCriterion $row) => $row->isOverride())
            ->keyBy('project_grade_criterion_id');

        $rows = [];
        foreach ($shared as $criterion) {
            $override = $overrides->get($criterion->project_grade_criterion_id);
            if ($override && $override->is_excluded) {
                continue;
            }

            $rows[] = [
                'key' => 'shared:'.$criterion->project_grade_criterion_id,
                'kind' => 'shared',
                'id' => (int) $criterion->project_grade_criterion_id,
                'title' => $override && $override->title ? $override->title : $criterion->title,
                'max_points' => round((float) ($override ? $override->max_points : $criterion->max_points), 2),
            ];
        }

        foreach ($teamRows as $row) {
            if ($row->isOverride() || $row->is_excluded) {
                continue;
            }

            $rows[] = [
                'key' => 'team:'.$row->project_team_grade_criterion_id,
                'kind' => 'team',
                'id' => (int) $row->project_team_grade_criterion_id,
                'title' => (string) $row->title,
                'max_points' => round((float) $row->max_points, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{max_points:float}>|null  $criteria
     */
    public function effectiveMaxPoints(ProjectAssessment $assessment, Project $project, ?array $criteria = null): float
    {
        $criteria ??= $this->effectiveCriteria($assessment, $project);
        if ($criteria === []) {
            return $this->maxPoints($assessment);
        }

        return round(array_sum(array_column($criteria, 'max_points')), 2);
    }

    /**
     * Per-criterion scores for one team, for the grades screen and the student
     * rubric breakdown. `points` is null when the team has not been graded yet.
     *
     * @return list<array{key:string, kind:string, id:int, title:string, max_points:float, points:?float, percent:?float}>
     */
    public function criterionBreakdown(ProjectAssessment $assessment, Project $project): array
    {
        $criteria = $this->effectiveCriteria($assessment, $project);
        if ($criteria === []) {
            return [];
        }

        $grade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        $sharedScores = $grade
            ? $grade->scores()->get()->keyBy('project_grade_criterion_id')
            : collect();
        $teamScores = $grade
            ? $grade->teamCriterionScores()->get()->keyBy('project_team_grade_criterion_id')
            : collect();

        $rows = [];
        foreach ($criteria as $criterion) {
            $score = $criterion['kind'] === 'shared'
                ? $sharedScores->get($criterion['id'])
                : $teamScores->get($criterion['id']);

            $points = $score ? round((float) $score->points, 2) : null;
            $rows[] = $criterion + [
                'points' => $points,
                'percent' => $points !== null && $criterion['max_points'] > 0
                    ? round($points / $criterion['max_points'] * 100, 2)
                    : null,
            ];
        }

        return $rows;
    }

    public function teamHasCustomRubric(Project $project): bool
    {
        return ProjectTeamGradeCriterion::query()
            ->where('project_id', $project->project_id)
            ->exists();
    }

    public function resetTeamCriteria(ProjectAssessment $assessment, Project $project, User $actor): Project
    {
        if ((int) $project->project_assessment_id !== (int) $assessment->project_assessment_id) {
            abort(404);
        }

        return DB::transaction(function () use ($assessment, $project, $actor) {
            ProjectTeamGradeCriterion::query()
                ->where('project_id', $project->project_id)
                ->delete();

            $this->pruneStaleTeamScores($project);
            $this->recalculateTeamGradeFor($assessment->load('criteria'), $project);

            AuditLogService::recordEvent('project.team_criteria_reset', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'actor_user_id' => $actor->user_id,
            ]);

            return $project->fresh();
        });
    }

    /**
     * Replace one team's rubric deviations. The effective criteria must still sum
     * to the assessment maximum so every team is graded out of the same total.
     *
     * @param  list<array{project_grade_criterion_id?:int|string|null, title?:?string, max_points?:float|int|string|null, is_excluded?:mixed}>  $rows
     */
    public function syncTeamCriteria(
        ProjectAssessment $assessment,
        Project $project,
        array $rows,
        User $actor,
    ): Project {
        if ((int) $project->project_assessment_id !== (int) $assessment->project_assessment_id) {
            abort(404);
        }

        $assessment->load('criteria');
        if ($assessment->criteria->isEmpty()) {
            throw ValidationException::withMessages([
                'team_criteria' => [__('projects.team_rubric_needs_shared_criteria')],
            ]);
        }

        $sharedIds = $assessment->criteria
            ->pluck('project_grade_criterion_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $prepared = [];
        $seenSharedIds = [];
        foreach (array_values($rows) as $index => $row) {
            $sharedId = (int) ($row['project_grade_criterion_id'] ?? 0);
            $excluded = (bool) ($row['is_excluded'] ?? false);
            $title = isset($row['title']) ? trim((string) $row['title']) : null;
            $rawMax = $row['max_points'] ?? null;

            if ($sharedId > 0) {
                if (! in_array($sharedId, $sharedIds, true)) {
                    abort(404);
                }
                if (in_array($sharedId, $seenSharedIds, true)) {
                    throw ValidationException::withMessages([
                        'team_criteria' => [__('projects.team_rubric_duplicate_override')],
                    ]);
                }
                $seenSharedIds[] = $sharedId;
            } else {
                if ($excluded) {
                    continue;
                }
                if ($title === null || $title === '') {
                    continue;
                }
            }

            $max = $excluded ? 0.0 : round((float) $rawMax, 2);
            if (! $excluded && $max <= 0) {
                throw ValidationException::withMessages([
                    'team_criteria' => [__('projects.criterion_max_required')],
                ]);
            }

            $prepared[] = [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'project_grade_criterion_id' => $sharedId > 0 ? $sharedId : null,
                'title' => $title ?: null,
                'max_points' => $max,
                'is_excluded' => $excluded,
                'sort_order' => $index,
            ];
        }

        return DB::transaction(function () use ($assessment, $project, $prepared, $actor) {
            ProjectTeamGradeCriterion::query()
                ->where('project_id', $project->project_id)
                ->delete();

            foreach ($prepared as $attributes) {
                ProjectTeamGradeCriterion::create($attributes);
            }

            $effective = $this->effectiveCriteria($assessment, $project);
            if ($effective === []) {
                throw ValidationException::withMessages([
                    'team_criteria' => [__('projects.team_rubric_empty')],
                ]);
            }

            $expected = $this->maxPoints($assessment);
            $actual = round(array_sum(array_column($effective, 'max_points')), 2);
            if (abs($actual - $expected) > 0.001) {
                throw ValidationException::withMessages([
                    'team_criteria' => [__('projects.team_rubric_sum_mismatch', [
                        'total' => number_format($actual, 2, '.', ''),
                        'max' => number_format($expected, 2, '.', ''),
                    ])],
                ]);
            }

            $this->pruneStaleTeamScores($project);
            $this->recalculateTeamGradeFor($assessment, $project);

            AuditLogService::recordEvent('project.team_criteria_synced', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'actor_user_id' => $actor->user_id,
                'row_count' => count($prepared),
            ]);

            return $project->fresh();
        });
    }

    public function passed(float $percent, ProjectAssessment $assessment): bool
    {
        return $percent >= (float) $assessment->passing_percent;
    }

    /**
     * @param  list<array{project_grade_criterion_id?:int|string|null, title?:string, max_points?:float|int|string}>  $rows
     */
    public function syncCriteria(ProjectAssessment $assessment, array $rows): ProjectAssessment
    {
        return DB::transaction(function () use ($assessment, $rows) {
            $keepIds = [];
            foreach (array_values($rows) as $index => $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $max = round((float) ($row['max_points'] ?? 0), 2);
                if ($max <= 0) {
                    throw ValidationException::withMessages([
                        'criteria' => [__('projects.criterion_max_required')],
                    ]);
                }

                $id = (int) ($row['project_grade_criterion_id'] ?? 0);
                $criterion = $id > 0
                    ? $assessment->criteria()->whereKey($id)->first()
                    : null;

                if ($criterion) {
                    $criterion->update([
                        'title' => $title,
                        'max_points' => $max,
                        'sort_order' => $index,
                    ]);
                } else {
                    $criterion = ProjectGradeCriterion::create([
                        'project_assessment_id' => $assessment->project_assessment_id,
                        'title' => $title,
                        'max_points' => $max,
                        'sort_order' => $index,
                    ]);
                }

                $keepIds[] = (int) $criterion->project_grade_criterion_id;
            }

            $removed = $assessment->criteria()
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('project_grade_criterion_id', $keepIds))
                ->when($keepIds === [], fn ($q) => $q)
                ->get();

            foreach ($removed as $criterion) {
                ProjectTeamGradeScore::query()
                    ->where('project_grade_criterion_id', $criterion->project_grade_criterion_id)
                    ->delete();
                $criterion->delete();
            }

            // Per-team deviations are expressed against the shared rubric, so a
            // shared-rubric edit invalidates them rather than silently skewing totals.
            $clearedTeamRows = ProjectTeamGradeCriterion::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->count();
            if ($clearedTeamRows > 0) {
                ProjectTeamGradeCriterion::query()
                    ->where('project_assessment_id', $assessment->project_assessment_id)
                    ->delete();
                foreach ($assessment->teamGrades()->get() as $grade) {
                    $grade->teamCriterionScores()->delete();
                }
            }

            $assessment->unsetRelation('criteria');
            $this->refreshAssessmentMaxPoints($assessment);
            $this->recalculateTeamGrades($assessment);

            AuditLogService::recordEvent('project.criteria_synced', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'criterion_count' => count($keepIds),
                'max_points' => $this->maxPoints($assessment->fresh('criteria')),
                'cleared_team_criteria' => $clearedTeamRows,
            ]);

            return $assessment->fresh(['criteria']);
        });
    }

    /**
     * @param  array{max_points?:float|int|string, passing_percent?:int|string}  $data
     */
    public function updateScale(ProjectAssessment $assessment, array $data): ProjectAssessment
    {
        $payload = [];
        if (array_key_exists('passing_percent', $data) && $data['passing_percent'] !== null && $data['passing_percent'] !== '') {
            $payload['passing_percent'] = (int) $data['passing_percent'];
        }

        $hasCriteria = $assessment->criteria()->exists();
        if (! $hasCriteria && array_key_exists('max_points', $data) && $data['max_points'] !== null && $data['max_points'] !== '') {
            $max = round((float) $data['max_points'], 2);
            if ($max <= 0) {
                throw ValidationException::withMessages([
                    'max_points' => [__('projects.max_points_required')],
                ]);
            }
            $payload['max_points'] = $max;
        }

        if ($payload !== []) {
            $assessment->update($payload);
            $this->recalculateTeamGrades($assessment->fresh('criteria'));
        }

        return $assessment->fresh(['criteria']);
    }

    /**
     * @param  array<int|string, float|int|string|null>  $scoresByCriterionId
     */
    public function gradeTeam(
        ProjectAssessment $assessment,
        Project $project,
        array $scoresByCriterionId,
        User $grader,
        ?string $notes = null,
        ?float $points = null,
    ): ProjectTeamGrade {
        if ((int) $project->project_assessment_id !== (int) $assessment->project_assessment_id) {
            abort(404);
        }

        $assessment->load('criteria');
        $criteria = $this->effectiveCriteria($assessment, $project);
        $max = $this->maxPoints($assessment);
        if ($max <= 0) {
            throw ValidationException::withMessages([
                'points' => [__('projects.max_points_required')],
            ]);
        }

        $scoreRows = [];
        if ($criteria !== []) {
            $total = 0.0;
            foreach ($criteria as $criterion) {
                $raw = $this->scoreForCriterion($scoresByCriterionId, $criterion);
                if ($raw === null || $raw === '') {
                    throw ValidationException::withMessages([
                        'scores' => [__('projects.criterion_score_required', ['title' => $criterion['title']])],
                    ]);
                }
                $score = round((float) $raw, 2);
                $criterionMax = $criterion['max_points'];
                if ($score < 0 || $score > $criterionMax) {
                    throw ValidationException::withMessages([
                        'scores' => [__('projects.criterion_score_range', [
                            'title' => $criterion['title'],
                            'max' => number_format($criterionMax, 2, '.', ''),
                        ])],
                    ]);
                }
                $scoreRows[$criterion['key']] = $score;
                $total += $score;
            }
            $total = round($total, 2);
        } else {
            if ($points === null) {
                throw ValidationException::withMessages([
                    'points' => [__('projects.team_points_required')],
                ]);
            }
            $total = round($points, 2);
            if ($total < 0 || $total > $max) {
                throw ValidationException::withMessages([
                    'points' => [__('projects.points_range', ['max' => number_format($max, 2, '.', '')])],
                ]);
            }
        }

        $percent = $this->percentFor($total, $assessment);

        return DB::transaction(function () use ($assessment, $project, $grader, $notes, $scoreRows, $total, $percent) {
            $grade = ProjectTeamGrade::updateOrCreate(
                ['project_id' => $project->project_id],
                [
                    'project_assessment_id' => $assessment->project_assessment_id,
                    'points' => $total,
                    'percent' => $percent,
                    'notes' => $notes,
                    'graded_by_user_id' => $grader->user_id,
                    'graded_at' => now(),
                ]
            );

            $this->storeCriterionScores($grade, $scoreRows);

            $this->propagateTeamGrade($assessment, $project, $grade, $grader);

            AuditLogService::recordEvent('project.team_graded', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'points' => $total,
                'percent' => $percent,
            ]);

            return $grade->fresh(['scores']);
        });
    }

    public function gradeStudent(
        ProjectAssessment $assessment,
        User $student,
        float $points,
        User $grader,
    ): ProjectMemberGrade {
        $membership = $assessment->activeMembershipFor((int) $student->user_id);
        if (! $membership) {
            throw ValidationException::withMessages([
                'user_id' => [__('projects.not_assigned')],
            ]);
        }

        $max = $this->maxPoints($assessment->load('criteria'));
        $points = round($points, 2);
        if ($max <= 0 || $points < 0 || $points > $max) {
            throw ValidationException::withMessages([
                'points' => [__('projects.points_range', ['max' => number_format(max($max, 0), 2, '.', '')])],
            ]);
        }

        $percent = $this->percentFor($points, $assessment);

        $grade = ProjectMemberGrade::updateOrCreate(
            [
                'project_assessment_id' => $assessment->project_assessment_id,
                'user_id' => $student->user_id,
            ],
            [
                'project_id' => $membership->project_id,
                'points' => $points,
                'percent' => $percent,
                'source' => ProjectMemberGrade::SOURCE_OVERRIDE,
                'graded_by_user_id' => $grader->user_id,
                'graded_at' => now(),
            ]
        );

        AuditLogService::recordEvent('project.student_grade_overridden', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $membership->project_id,
            'user_id' => $student->user_id,
            'points' => $points,
            'percent' => $percent,
        ]);

        return $grade->fresh();
    }

    public function clearStudentOverride(
        ProjectAssessment $assessment,
        User $student,
        User $grader,
    ): ?ProjectMemberGrade {
        $existing = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $student->user_id)
            ->first();

        if (! $existing || ! $existing->isOverride()) {
            return $existing;
        }

        $membership = $assessment->activeMembershipFor((int) $student->user_id);
        $teamGrade = $membership
            ? ProjectTeamGrade::query()->where('project_id', $membership->project_id)->first()
            : null;

        if ($teamGrade && $membership) {
            $existing->update([
                'project_id' => $membership->project_id,
                'points' => $teamGrade->points,
                'percent' => $teamGrade->percent,
                'source' => ProjectMemberGrade::SOURCE_TEAM,
                'graded_by_user_id' => $grader->user_id,
                'graded_at' => now(),
            ]);
        } else {
            $existing->delete();
            $existing = null;
        }

        AuditLogService::recordEvent('project.student_grade_override_cleared', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'user_id' => $student->user_id,
        ]);

        return $existing?->fresh();
    }

    public function inheritTeamGradeIfNeeded(
        ProjectAssessment $assessment,
        Project $project,
        User $user,
    ): void {
        $existing = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $user->user_id)
            ->first();

        if ($existing && $existing->isOverride()) {
            if ((int) $existing->project_id !== (int) $project->project_id) {
                $existing->update(['project_id' => $project->project_id]);
            }

            return;
        }

        $teamGrade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        if (! $teamGrade) {
            $existing?->delete();

            return;
        }

        ProjectMemberGrade::updateOrCreate(
            [
                'project_assessment_id' => $assessment->project_assessment_id,
                'user_id' => $user->user_id,
            ],
            [
                'project_id' => $project->project_id,
                'points' => $teamGrade->points,
                'percent' => $teamGrade->percent,
                'source' => ProjectMemberGrade::SOURCE_TEAM,
                'graded_by_user_id' => $teamGrade->graded_by_user_id,
                'graded_at' => $teamGrade->graded_at ?? now(),
            ]
        );
    }

    public function announce(ProjectAssessment $assessment, User $actor): ProjectAssessment
    {
        if ($assessment->areResultsAnnounced()) {
            abort(409, __('projects.results_already_announced'));
        }

        $assessment->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $actor->user_id,
        ]);

        AuditLogService::recordEvent('project.results_announced', [
            'actor_user_id' => $actor->user_id,
            'project_assessment_id' => $assessment->project_assessment_id,
            'course_id' => $assessment->course_id,
            'module_id' => $assessment->module_id,
        ]);

        return $assessment->fresh();
    }

    public function deleteGradesForAssessment(ProjectAssessment $assessment): void
    {
        foreach ($assessment->teamGrades as $grade) {
            $grade->scores()->delete();
            $grade->teamCriterionScores()->delete();
            $grade->delete();
        }
        $assessment->memberGrades()->delete();
        ProjectTeamGradeCriterion::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->delete();
        $assessment->criteria()->delete();
    }

    public function deleteGradesForProject(Project $project): void
    {
        $grade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        $grade?->scores()->delete();
        $grade?->teamCriterionScores()->delete();
        $grade?->delete();
        ProjectTeamGradeCriterion::query()->where('project_id', $project->project_id)->delete();
        ProjectMemberGrade::query()->where('project_id', $project->project_id)->delete();
    }

    private function propagateTeamGrade(
        ProjectAssessment $assessment,
        Project $project,
        ProjectTeamGrade $grade,
        User $grader,
    ): void {
        foreach ($project->activeMemberships as $membership) {
            $existing = ProjectMemberGrade::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->where('user_id', $membership->user_id)
                ->first();

            if ($existing && $existing->isOverride()) {
                continue;
            }

            ProjectMemberGrade::updateOrCreate(
                [
                    'project_assessment_id' => $assessment->project_assessment_id,
                    'user_id' => $membership->user_id,
                ],
                [
                    'project_id' => $project->project_id,
                    'points' => $grade->points,
                    'percent' => $grade->percent,
                    'source' => ProjectMemberGrade::SOURCE_TEAM,
                    'graded_by_user_id' => $grader->user_id,
                    'graded_at' => now(),
                ]
            );
        }
    }

    /**
     * Accepts `shared:{id}` / `team:{id}` keys and, for shared criteria, the bare
     * criterion id the v1 grading form still posts.
     *
     * @param  array<int|string, float|int|string|null>  $scores
     * @param  array{key:string, kind:string, id:int}  $criterion
     */
    private function scoreForCriterion(array $scores, array $criterion): float|int|string|null
    {
        foreach ([$criterion['key'], $criterion['id'], (string) $criterion['id']] as $candidate) {
            if ($criterion['kind'] !== 'shared' && $candidate !== $criterion['key']) {
                continue;
            }
            if (array_key_exists($candidate, $scores)) {
                return $scores[$candidate];
            }
        }

        return null;
    }

    /**
     * @param  array<string, float>  $scoreRows  keyed by effective criterion key
     */
    private function storeCriterionScores(ProjectTeamGrade $grade, array $scoreRows): void
    {
        $keepSharedIds = [];
        $keepTeamIds = [];

        foreach ($scoreRows as $key => $score) {
            [$kind, $id] = explode(':', $key, 2);
            if ($kind === 'shared') {
                $row = ProjectTeamGradeScore::updateOrCreate(
                    [
                        'project_team_grade_id' => $grade->project_team_grade_id,
                        'project_grade_criterion_id' => (int) $id,
                    ],
                    ['points' => $score]
                );
                $keepSharedIds[] = (int) $row->project_team_grade_score_id;

                continue;
            }

            $row = ProjectTeamCriterionScore::updateOrCreate(
                [
                    'project_team_grade_id' => $grade->project_team_grade_id,
                    'project_team_grade_criterion_id' => (int) $id,
                ],
                ['points' => $score]
            );
            $keepTeamIds[] = (int) $row->project_team_criterion_score_id;
        }

        $grade->scores()
            ->when($keepSharedIds !== [], fn ($q) => $q->whereNotIn('project_team_grade_score_id', $keepSharedIds))
            ->delete();

        $grade->teamCriterionScores()
            ->when($keepTeamIds !== [], fn ($q) => $q->whereNotIn('project_team_criterion_score_id', $keepTeamIds))
            ->delete();
    }

    /**
     * Drops scores that no longer map to a criterion in the team's rubric.
     */
    private function pruneStaleTeamScores(Project $project): void
    {
        $grade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        if (! $grade) {
            return;
        }

        $liveIds = ProjectTeamGradeCriterion::query()
            ->where('project_id', $project->project_id)
            ->pluck('project_team_grade_criterion_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $grade->teamCriterionScores()
            ->when($liveIds !== [], fn ($q) => $q->whereNotIn('project_team_grade_criterion_id', $liveIds))
            ->delete();
    }

    private function recalculateTeamGradeFor(ProjectAssessment $assessment, Project $project): void
    {
        $grade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        if (! $grade) {
            return;
        }

        $criteria = $this->effectiveCriteria($assessment, $project);
        $max = $this->maxPoints($assessment);

        if ($criteria === []) {
            $total = min(round((float) $grade->points, 2), $max);
        } else {
            $sharedScores = $grade->scores()->get()->keyBy('project_grade_criterion_id');
            $teamScores = $grade->teamCriterionScores()->get()->keyBy('project_team_grade_criterion_id');

            $total = 0.0;
            foreach ($criteria as $criterion) {
                $points = $criterion['kind'] === 'shared'
                    ? (float) ($sharedScores->get($criterion['id'])?->points ?? 0)
                    : (float) ($teamScores->get($criterion['id'])?->points ?? 0);
                $total += min($points, $criterion['max_points']);
            }
            $total = round($total, 2);
        }

        $percent = $max > 0 ? $this->percentFor($total, $assessment) : 0.0;
        $grade->update(['points' => $total, 'percent' => $percent]);

        ProjectMemberGrade::query()
            ->where('project_id', $grade->project_id)
            ->where('source', ProjectMemberGrade::SOURCE_TEAM)
            ->update(['points' => $total, 'percent' => $percent]);
    }

    private function refreshAssessmentMaxPoints(ProjectAssessment $assessment): void
    {
        $criteria = $assessment->criteria()->get();
        if ($criteria->isEmpty()) {
            return;
        }

        $assessment->update(['max_points' => round((float) $criteria->sum('max_points'), 2)]);
    }

    private function recalculateTeamGrades(ProjectAssessment $assessment): void
    {
        $assessment->load('criteria');
        foreach ($assessment->projects()->get() as $project) {
            $this->recalculateTeamGradeFor($assessment, $project);
        }
    }
}
