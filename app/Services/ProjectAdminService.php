<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectPhase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAdminService
{
    /**
     * @param  array{
     *     course_id:int,
     *     module_id:int,
     *     title:string,
     *     description?:?string,
     *     min_team_size:int,
     *     max_team_size:int,
     *     max_points?:float|int,
     *     passing_percent?:int,
     *     criteria?:list<array{title:string, max_points:float|int}>,
     *     project_count?:int,
     *     project_titles?:list<string>,
     *     requirements?:?string,
     *     phases?:list<array{title:string, description?:?string, deadline?:?string}>,
     *     deliverables?:list<array{title:string, description?:?string, due_at?:?string}>,
     * }  $data
     */
    public function createAssessment(array $data, User $creator): ProjectAssessment
    {
        $this->assertTeamSizes((int) $data['min_team_size'], (int) $data['max_team_size']);

        return DB::transaction(function () use ($data, $creator) {
            $assessment = ProjectAssessment::create([
                'course_id' => $data['course_id'],
                'module_id' => $data['module_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'min_team_size' => $data['min_team_size'],
                'max_team_size' => $data['max_team_size'],
                'max_points' => $data['max_points'] ?? 100,
                'passing_percent' => $data['passing_percent'] ?? 50,
                'is_published' => false,
                'created_by_user_id' => $creator->user_id,
            ]);

            if (! empty($data['criteria'])) {
                app(ProjectGradingService::class)->syncCriteria($assessment, $data['criteria']);
            }

            $count = max(1, (int) ($data['project_count'] ?? 1));
            $titles = $this->resolveTitles($data['title'], $count, $data['project_titles'] ?? []);

            foreach ($titles as $index => $title) {
                $this->createProject($assessment, [
                    'title' => $title,
                    'requirements' => $data['requirements'] ?? null,
                    'sort_order' => $index,
                    'phases' => $data['phases'] ?? [],
                    'deliverables' => $data['deliverables'] ?? [],
                ]);
            }

            AuditLogService::recordEvent('project.assessment_created', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'course_id' => $assessment->course_id,
                'module_id' => $assessment->module_id,
                'project_count' => $count,
            ]);

            return $assessment->fresh(['projects.phases', 'projects.deliverables']);
        });
    }

    /**
     * @param  array{
     *     title:string,
     *     requirements?:?string,
     *     sort_order?:int,
     *     phases?:list<array{title:string, description?:?string, deadline?:?string}>,
     *     deliverables?:list<array{title:string, description?:?string, due_at?:?string}>,
     * }  $data
     */
    public function createProject(ProjectAssessment $assessment, array $data): Project
    {
        $project = Project::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'title' => $data['title'],
            'requirements' => $data['requirements'] ?? null,
            'status' => Project::STATUS_OPEN,
            'sort_order' => $data['sort_order'] ?? ((int) $assessment->projects()->max('sort_order') + 1),
        ]);

        $this->syncPhases($project, $data['phases'] ?? []);
        $this->syncDeliverables($project, $data['deliverables'] ?? []);

        return $project->fresh(['phases', 'deliverables']);
    }

    /**
     * @param  array{title?:string, description?:?string, min_team_size?:int, max_team_size?:int, max_points?:float|int, passing_percent?:int}  $data
     */
    public function updateAssessment(ProjectAssessment $assessment, array $data): ProjectAssessment
    {
        if (isset($data['min_team_size'], $data['max_team_size'])) {
            $this->assertTeamSizes((int) $data['min_team_size'], (int) $data['max_team_size']);
        }

        $assessment->update($data);

        return $assessment->fresh();
    }

    public function publish(ProjectAssessment $assessment, bool $published = true): ProjectAssessment
    {
        $assessment->update(['is_published' => $published]);

        AuditLogService::recordEvent($published ? 'project.assessment_published' : 'project.assessment_unpublished', [
            'project_assessment_id' => $assessment->project_assessment_id,
        ]);

        return $assessment->fresh();
    }

    public function deleteAssessment(ProjectAssessment $assessment): void
    {
        if ($assessment->memberships()->exists()) {
            throw ValidationException::withMessages([
                'assessment' => [__('projects.cannot_delete_with_members')],
            ]);
        }

        $id = $assessment->project_assessment_id;
        app(ProjectGradingService::class)->deleteGradesForAssessment($assessment);
        foreach ($assessment->projects as $project) {
            $project->phases()->delete();
            $project->deliverables()->delete();
            $project->delete();
        }
        $assessment->changeRequests()->delete();
        $assessment->delete();

        AuditLogService::recordEvent('project.assessment_deleted', [
            'project_assessment_id' => $id,
        ]);
    }

    /**
     * @param  array{title?:string, requirements?:?string, phases?:list<array>, deliverables?:list<array>}  $data
     */
    public function updateProject(Project $project, array $data): Project
    {
        $project->update(array_filter(
            [
                'title' => $data['title'] ?? null,
                'requirements' => $data['requirements'] ?? null,
            ],
            fn ($value) => $value !== null
        ));

        if (array_key_exists('phases', $data)) {
            $project->phases()->delete();
            $this->syncPhases($project, $data['phases'] ?? []);
        }

        if (array_key_exists('deliverables', $data)) {
            $project->deliverables()->delete();
            $this->syncDeliverables($project, $data['deliverables'] ?? []);
        }

        return $project->fresh(['phases', 'deliverables']);
    }

    public function deleteProject(Project $project): void
    {
        if ($project->memberships()->exists()) {
            throw ValidationException::withMessages([
                'project' => [__('projects.cannot_delete_with_members')],
            ]);
        }

        $id = $project->project_id;
        app(ProjectGradingService::class)->deleteGradesForProject($project);
        $project->phases()->delete();
        $project->deliverables()->delete();
        $project->delete();

        AuditLogService::recordEvent('project.deleted', [
            'project_id' => $id,
        ]);
    }

    /**
     * @param  list<string>  $explicit
     * @return list<string>
     */
    private function resolveTitles(string $baseTitle, int $count, array $explicit): array
    {
        $explicit = array_values(array_filter(array_map('trim', $explicit)));
        if (count($explicit) >= $count) {
            return array_slice($explicit, 0, $count);
        }

        $titles = [];
        for ($i = 1; $i <= $count; $i++) {
            $titles[] = $count === 1 ? $baseTitle : $baseTitle.' '.$i;
        }

        return $titles;
    }

    /**
     * @param  list<array{title?:string, description?:?string, deadline?:?string}>  $phases
     */
    private function syncPhases(Project $project, array $phases): void
    {
        foreach (array_values($phases) as $index => $phase) {
            $title = trim((string) ($phase['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            ProjectPhase::create([
                'project_id' => $project->project_id,
                'title' => $title,
                'description' => $phase['description'] ?? null,
                'deadline' => $phase['deadline'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array{title?:string, description?:?string, due_at?:?string}>  $deliverables
     */
    private function syncDeliverables(Project $project, array $deliverables): void
    {
        foreach (array_values($deliverables) as $index => $deliverable) {
            $title = trim((string) ($deliverable['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            ProjectDeliverable::create([
                'project_id' => $project->project_id,
                'title' => $title,
                'description' => $deliverable['description'] ?? null,
                'due_at' => $deliverable['due_at'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    private function assertTeamSizes(int $min, int $max): void
    {
        if ($min < 1 || $max < 1 || $max < $min) {
            throw ValidationException::withMessages([
                'max_team_size' => [__('projects.invalid_team_size')],
            ]);
        }
    }
}
