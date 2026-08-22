<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectTeamGrade;
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

            $assessment->unsetRelation('criteria');
            $this->refreshAssessmentMaxPoints($assessment);
            $this->recalculateTeamGrades($assessment);

            AuditLogService::recordEvent('project.criteria_synced', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'criterion_count' => count($keepIds),
                'max_points' => $this->maxPoints($assessment->fresh('criteria')),
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
        $criteria = $assessment->criteria;
        $max = $this->maxPoints($assessment);
        if ($max <= 0) {
            throw ValidationException::withMessages([
                'points' => [__('projects.max_points_required')],
            ]);
        }

        $scoreRows = [];
        if ($criteria->isNotEmpty()) {
            $total = 0.0;
            foreach ($criteria as $criterion) {
                $id = (int) $criterion->project_grade_criterion_id;
                $raw = $scoresByCriterionId[$id] ?? $scoresByCriterionId[(string) $id] ?? null;
                if ($raw === null || $raw === '') {
                    throw ValidationException::withMessages([
                        'scores' => [__('projects.criterion_score_required', ['title' => $criterion->title])],
                    ]);
                }
                $score = round((float) $raw, 2);
                $criterionMax = (float) $criterion->max_points;
                if ($score < 0 || $score > $criterionMax) {
                    throw ValidationException::withMessages([
                        'scores' => [__('projects.criterion_score_range', [
                            'title' => $criterion->title,
                            'max' => number_format($criterionMax, 2, '.', ''),
                        ])],
                    ]);
                }
                $scoreRows[$id] = $score;
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

            $keepIds = [];
            foreach ($scoreRows as $criterionId => $score) {
                $row = ProjectTeamGradeScore::updateOrCreate(
                    [
                        'project_team_grade_id' => $grade->project_team_grade_id,
                        'project_grade_criterion_id' => $criterionId,
                    ],
                    ['points' => $score]
                );
                $keepIds[] = (int) $row->project_team_grade_score_id;
            }

            $grade->scores()
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('project_team_grade_score_id', $keepIds))
                ->when($keepIds === [], fn ($q) => $q)
                ->delete();

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
            $grade->delete();
        }
        $assessment->memberGrades()->delete();
        $assessment->criteria()->delete();
    }

    public function deleteGradesForProject(Project $project): void
    {
        $grade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->first();
        $grade?->scores()->delete();
        $grade?->delete();
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
        $max = $this->maxPoints($assessment);
        foreach ($assessment->teamGrades()->with('scores')->get() as $grade) {
            if ($assessment->criteria->isNotEmpty()) {
                $total = 0.0;
                foreach ($assessment->criteria as $criterion) {
                    $score = $grade->scores->firstWhere(
                        'project_grade_criterion_id',
                        $criterion->project_grade_criterion_id
                    );
                    $total += (float) ($score?->points ?? 0);
                }
                $total = round($total, 2);
            } else {
                $total = min(round((float) $grade->points, 2), $max);
            }

            $percent = $max > 0 ? $this->percentFor($total, $assessment) : 0.0;
            $grade->update([
                'points' => $total,
                'percent' => $percent,
            ]);

            ProjectMemberGrade::query()
                ->where('project_id', $grade->project_id)
                ->where('source', ProjectMemberGrade::SOURCE_TEAM)
                ->update([
                    'points' => $total,
                    'percent' => $percent,
                ]);
        }
    }
}
