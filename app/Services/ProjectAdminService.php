<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectPhase;
use App\Models\User;
use Illuminate\Support\Carbon;
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
     *     join_closes_at?:?string,
     *     submission_due_at?:?string,
     *     seed_pool_size?:?int,
     *     sync_to_gradebook?:bool,
     *     criteria?:list<array{title:string, max_points:float|int}>,
     *     project_count?:int,
     *     project_titles?:list<string>,
     *     subprojects?:list<array{title:string, requirements?:?string, brief_main_title?:?string, brief_audience?:?string, brief_environment?:?string, brief_purpose?:?string}>,
     *     requirements?:?string,
     *     phases?:list<array{title:string, description?:?string, deadline?:?string}>,
     *     deliverables?:list<array{title:string, description?:?string, due_at?:?string}>,
     * }  $data
     */
    public function createAssessment(array $data, User $creator): ProjectAssessment
    {
        $this->assertTeamSizes((int) $data['min_team_size'], (int) $data['max_team_size']);
        $joinClosesAt = $this->normalizeJoinClosesAt($data['join_closes_at'] ?? null);
        $submissionDueAt = $this->normalizeSubmissionDueAt($data['submission_due_at'] ?? null, $joinClosesAt);

        $data['deliverables'] = $this->titledRows($data['deliverables'] ?? []);
        $data['phases'] = $this->titledRows($data['phases'] ?? []);
        if (($data['seed_canonical_slots'] ?? false) && $data['deliverables'] === []) {
            $data['deliverables'] = ProjectTeamWorkflowService::canonicalDeliverablePayload($submissionDueAt);
        }
        if (($data['seed_canonical_slots'] ?? false) && $data['phases'] === []) {
            $data['phases'] = ProjectTeamWorkflowService::canonicalPhasePayload($submissionDueAt);
        }

        return DB::transaction(function () use ($data, $creator, $joinClosesAt, $submissionDueAt) {
            $assessment = ProjectAssessment::create([
                'course_id' => $data['course_id'],
                'module_id' => $data['module_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'min_team_size' => $data['min_team_size'],
                'max_team_size' => $data['max_team_size'],
                'max_points' => $data['max_points'] ?? 100,
                'passing_percent' => $data['passing_percent'] ?? 50,
                'join_closes_at' => $joinClosesAt,
                'submission_due_at' => $submissionDueAt,
                'seed_pool_size' => $data['seed_pool_size'] ?? null,
                'sync_to_gradebook' => (bool) ($data['sync_to_gradebook'] ?? false),
                'is_published' => false,
                'created_by_user_id' => $creator->user_id,
            ]);

            if (! empty($data['criteria'])) {
                app(ProjectGradingService::class)->syncCriteria($assessment, $data['criteria']);
            }

            $subprojects = $this->resolveSubprojects($data);

            foreach ($subprojects as $index => $subproject) {
                $this->createProject($assessment, array_merge($subproject, [
                    'sort_order' => $index,
                    'phases' => $data['phases'] ?? [],
                    'deliverables' => $data['deliverables'] ?? [],
                ]));
            }

            $count = count($subprojects);

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
     *     brief_main_title?:?string,
     *     brief_audience?:?string,
     *     brief_environment?:?string,
     *     brief_purpose?:?string,
     *     sort_order?:int,
     *     phases?:list<array{title:string, description?:?string, deadline?:?string}>,
     *     deliverables?:list<array{title:string, description?:?string, due_at?:?string}>,
     * }  $data
     */
    public function createProject(ProjectAssessment $assessment, array $data): Project
    {
        $this->assertUniqueTitle($assessment, (string) $data['title']);

        $project = Project::create(array_merge([
            'project_assessment_id' => $assessment->project_assessment_id,
            'title' => $data['title'],
            'status' => Project::STATUS_OPEN,
            'sort_order' => $data['sort_order'] ?? ((int) $assessment->projects()->max('sort_order') + 1),
        ], $this->briefPersistAttributes($data)));

        $this->syncPhases($project, $data['phases'] ?? []);
        $this->syncDeliverables($project, $data['deliverables'] ?? []);

        return $project->fresh(['phases', 'deliverables']);
    }

    /**
     * @param  array{title?:string, description?:?string, min_team_size?:int, max_team_size?:int, max_points?:float|int, passing_percent?:int, join_closes_at?:?string, seed_pool_size?:?int, sync_to_gradebook?:bool}  $data
     */
    public function updateAssessment(ProjectAssessment $assessment, array $data): ProjectAssessment
    {
        if (isset($data['min_team_size'], $data['max_team_size'])) {
            $this->assertTeamSizes((int) $data['min_team_size'], (int) $data['max_team_size']);
        }

        if (array_key_exists('join_closes_at', $data)) {
            $data['join_closes_at'] = $this->normalizeJoinClosesAt($data['join_closes_at']);
        }

        if (array_key_exists('submission_due_at', $data)) {
            if ($data['submission_due_at'] === null || $data['submission_due_at'] === '') {
                unset($data['submission_due_at']);
            } else {
                $data['submission_due_at'] = $this->normalizeSubmissionDueAt(
                    $data['submission_due_at'],
                    isset($data['join_closes_at'])
                        ? (string) $data['join_closes_at']
                        : optional($assessment->join_closes_at)?->toDateTimeString(),
                    allowPast: true,
                );
            }
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
        app(ProjectSubmissionService::class)->deleteForAssessment($assessment);
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
     * @param  array{title?:string, requirements?:?string, brief_main_title?:?string, brief_audience?:?string, brief_environment?:?string, brief_purpose?:?string, phases?:list<array>, deliverables?:list<array>}  $data
     */
    public function updateProject(Project $project, array $data): Project
    {
        if (isset($data['title'])) {
            $this->assertUniqueTitle($project->assessment ?? $project->assessment()->firstOrFail(), (string) $data['title'], (int) $project->project_id);
        }

        $payload = array_filter(
            [
                'title' => $data['title'] ?? null,
            ],
            fn ($value) => $value !== null
        );

        if ($this->rowHasBriefKeys($data) || array_key_exists('requirements', $data)) {
            $payload = array_merge($payload, $this->briefPersistAttributes($data, $project));
        }

        $project->update($payload);

        if (array_key_exists('phases', $data)) {
            $project->phases()->delete();
            $this->syncPhases($project, $data['phases'] ?? []);
        }

        if (array_key_exists('deliverables', $data)) {
            $this->assertNoDeliverableSubmissions($project);
            $project->deliverables()->delete();
            $this->syncDeliverables($project, $data['deliverables'] ?? []);
        }

        return $project->fresh(['phases', 'deliverables']);
    }

    public function updateTeamWorkspace(Project $project, array $data): Project
    {
        $url = isset($data['team_workspace_url']) ? trim((string) $data['team_workspace_url']) : null;
        if ($url !== null && $url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'team_workspace_url' => [__('projects.workspace_url_invalid')],
            ]);
        }

        $provider = (string) ($data['workspace_provider'] ?? $project->workspaceProvider());
        if (! in_array($provider, Project::workspaceProviders(), true)) {
            throw ValidationException::withMessages([
                'workspace_provider' => [__('projects.workspace_provider_invalid')],
            ]);
        }

        if ($url !== null && $url !== '' && ! Project::workspaceUrlMatchesProvider($url, $provider)) {
            throw ValidationException::withMessages([
                'team_workspace_url' => [__('projects.workspace_host_mismatch')],
            ]);
        }

        $project->update([
            'workspace_provider' => $provider,
            'team_workspace_url' => ($url === '' ? null : $url),
            'team_announcement' => isset($data['team_announcement'])
                ? (trim((string) $data['team_announcement']) ?: null)
                : $project->team_announcement,
        ]);

        AuditLogService::recordEvent('project.workspace_updated', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'workspace_provider' => $provider,
        ]);

        return $project->fresh();
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
        app(ProjectSubmissionService::class)->deleteForProject($project);
        $project->phases()->delete();
        $project->deliverables()->delete();
        $project->delete();

        AuditLogService::recordEvent('project.deleted', [
            'project_id' => $id,
        ]);
    }

    /**
     * Each team is one unique subproject. Explicit `subprojects` win; otherwise
     * `project_titles` or numbered titles from the assessment name.
     *
     * @return list<array{title:string, requirements:?string, brief_main_title:?string, brief_audience:?string, brief_environment:?string, brief_purpose:?string}>
     */
    private function resolveSubprojects(array $data): array
    {
        $sharedRequirements = $data['requirements'] ?? null;
        $rows = [];

        $submittedSubprojects = array_key_exists('subprojects', $data);

        foreach ($data['subprojects'] ?? [] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $rows[] = array_merge(
                ['title' => $title],
                $this->briefPersistAttributes($row, sharedRequirements: $sharedRequirements)
            );
        }

        if ($submittedSubprojects && $rows === []) {
            throw ValidationException::withMessages([
                'title' => [__('projects.subproject_title_required')],
            ]);
        }

        if ($rows === []) {
            $count = max(1, (int) ($data['project_count'] ?? 1));
            $explicit = array_values(array_filter(array_map('trim', $data['project_titles'] ?? [])));
            if (count($explicit) >= $count) {
                $titles = array_slice($explicit, 0, $count);
            } else {
                $titles = [];
                for ($i = 1; $i <= $count; $i++) {
                    $titles[] = $count === 1 ? $data['title'] : $data['title'].' '.$i;
                }
            }
            foreach ($titles as $title) {
                $rows[] = array_merge(
                    ['title' => $title],
                    $this->briefPersistAttributes([], sharedRequirements: $sharedRequirements)
                );
            }
        }

        $this->assertUniqueTitleList(array_column($rows, 'title'));

        return $rows;
    }

    /**
     * Persist the four team-brief fields. When any brief field is set, compose
     * `requirements` from them so legacy views still have a blob. Otherwise keep
     * the posted/shared requirements text (existing assessments).
     *
     * @param  array<string, mixed>  $data
     * @return array{brief_main_title:?string, brief_audience:?string, brief_environment:?string, brief_purpose:?string, requirements:?string}
     */
    private function briefPersistAttributes(array $data, ?Project $existing = null, mixed $sharedRequirements = null): array
    {
        $postedBrief = $this->rowHasBriefKeys($data);
        $brief = $postedBrief
            ? Project::briefFromRow($data)
            : ($existing?->briefAttributes() ?? Project::briefFromRow([]));

        $hasBriefValues = collect($brief)->contains(fn ($value) => $value !== null);

        if ($hasBriefValues) {
            $requirements = Project::composeRequirements($brief);
            $existingReq = trim((string) ($existing?->requirements ?? ''));
            // Keep the original blob when the four fields are a truncated prefix
            // of it (legacy descriptions longer than 255 characters).
            if ($existingReq !== ''
                && $requirements !== null
                && $existingReq !== $requirements
                && str_starts_with($existingReq, $requirements)
            ) {
                $requirements = $existingReq;
            }
        } elseif (array_key_exists('requirements', $data)) {
            $trimmed = trim((string) ($data['requirements'] ?? ''));
            $requirements = $trimmed === '' ? null : $trimmed;
        } elseif ($sharedRequirements !== null && trim((string) $sharedRequirements) !== '') {
            $requirements = trim((string) $sharedRequirements);
        } elseif ($postedBrief) {
            // Empty brief boxes on edit must not wipe the stored description.
            $requirements = $existing?->requirements;
        } else {
            $requirements = $existing?->requirements;
        }

        return [
            'brief_main_title' => $brief[Project::BRIEF_MAIN_TITLE] ?? null,
            'brief_audience' => $brief[Project::BRIEF_AUDIENCE] ?? null,
            'brief_environment' => $brief[Project::BRIEF_ENVIRONMENT] ?? null,
            'brief_purpose' => $brief[Project::BRIEF_PURPOSE] ?? null,
            'requirements' => $requirements,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rowHasBriefKeys(array $data): bool
    {
        foreach (Project::BRIEF_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $titles
     */
    private function assertUniqueTitleList(array $titles): void
    {
        $seen = [];
        foreach ($titles as $title) {
            $key = mb_strtolower(trim($title));
            if ($key === '') {
                throw ValidationException::withMessages([
                    'title' => [__('projects.subproject_title_required')],
                ]);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'title' => [__('projects.subproject_duplicate', ['title' => $title])],
                ]);
            }
            $seen[$key] = true;
        }
    }

    private function assertUniqueTitle(ProjectAssessment $assessment, string $title, ?int $exceptProjectId = null): void
    {
        $needle = mb_strtolower(trim($title));
        if ($needle === '') {
            throw ValidationException::withMessages([
                'title' => [__('projects.subproject_title_required')],
            ]);
        }

        $taken = $assessment->projects()
            ->when($exceptProjectId, fn ($q) => $q->where('project_id', '!=', $exceptProjectId))
            ->pluck('title')
            ->contains(fn ($existing) => mb_strtolower(trim((string) $existing)) === $needle);

        if ($taken) {
            throw ValidationException::withMessages([
                'title' => [__('projects.subproject_duplicate', ['title' => $title])],
            ]);
        }
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
     * @param  list<array{title?:string, description?:?string, instructions?:?string, due_at?:?string, submission_type?:?string, file_mode?:?string, is_required?:mixed, allow_late?:mixed}>  $deliverables
     */
    private function syncDeliverables(Project $project, array $deliverables): void
    {
        foreach (array_values($deliverables) as $index => $deliverable) {
            $title = trim((string) ($deliverable['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $type = (string) ($deliverable['submission_type'] ?? ProjectDeliverable::TYPE_PDF);
            if (! in_array($type, ProjectDeliverable::submissionTypes(), true)) {
                throw ValidationException::withMessages([
                    'deliverables' => [__('projects.submission_type_invalid')],
                ]);
            }

            $fileMode = (string) ($deliverable['file_mode'] ?? ProjectDeliverable::FILE_MODE_SINGLE);
            if (! in_array($fileMode, ProjectDeliverable::fileModes(), true)) {
                $fileMode = ProjectDeliverable::FILE_MODE_SINGLE;
            }

            ProjectDeliverable::create([
                'project_id' => $project->project_id,
                'title' => $title,
                'description' => $deliverable['description'] ?? null,
                'instructions' => $deliverable['instructions'] ?? null,
                'due_at' => $deliverable['due_at'] ?? null,
                'sort_order' => $index,
                'submission_type' => $type,
                'file_mode' => $fileMode,
                'is_required' => (bool) ($deliverable['is_required'] ?? true),
                'allow_late' => (bool) ($deliverable['allow_late'] ?? true),
                'max_points' => isset($deliverable['max_points']) && $deliverable['max_points'] !== ''
                    ? round((float) $deliverable['max_points'], 2)
                    : null,
                'slot_key' => $this->normalizedSlotKey($deliverable['slot_key'] ?? null),
            ]);
        }
    }

    /**
     * Deliverable rows are replaced wholesale on edit, which would orphan the
     * team submissions pointing at them. Block the edit instead of deleting work.
     */
    private function assertNoDeliverableSubmissions(Project $project): void
    {
        $hasSubmissions = ProjectDeliverableSubmission::query()
            ->where('project_id', $project->project_id)
            ->exists();

        if ($hasSubmissions) {
            throw ValidationException::withMessages([
                'deliverables' => [__('projects.cannot_replace_deliverables_with_submissions')],
            ]);
        }
    }

    /**
     * v2 requires a join window. Legacy assessments created before v2 keep their
     * null window, but a value supplied here must be a future instant.
     */
    private function normalizeJoinClosesAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                'join_closes_at' => [__('projects.join_closes_at_required')],
            ]);
        }

        try {
            $when = Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'join_closes_at' => [__('projects.join_closes_at_required')],
            ]);
        }

        if ($when->isPast()) {
            throw ValidationException::withMessages([
                'join_closes_at' => [__('projects.join_closes_at_future')],
            ]);
        }

        return $when->toDateTimeString();
    }

    private function normalizeSubmissionDueAt(mixed $value, ?string $joinClosesAt, bool $allowPast = false): ?string
    {
        if ($value === null || $value === '') {
            if ($joinClosesAt === null) {
                return null;
            }

            return Carbon::parse($joinClosesAt)->addWeeks(4)->toDateTimeString();
        }

        try {
            $when = Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'submission_due_at' => [__('projects.submission_due_required')],
            ]);
        }

        if (! $allowPast && $when->isPast()) {
            throw ValidationException::withMessages([
                'submission_due_at' => [__('projects.submission_due_future')],
            ]);
        }

        return $when->toDateTimeString();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function titledRows(array $rows): array
    {
        $kept = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }
            $kept[] = $row;
        }

        return $kept;
    }

    private function normalizedSlotKey(mixed $value): ?string
    {
        $key = is_string($value) ? trim($value) : '';
        if ($key === '' || ! in_array($key, ProjectDeliverable::canonicalSlotKeys(), true)) {
            return null;
        }

        return $key;
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
