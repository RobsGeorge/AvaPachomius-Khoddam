<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAssignmentService
{
    public function __construct(
        private ProjectNotificationService $notifications,
    ) {}

    public function assignStudent(
        ProjectAssessment $assessment,
        User $user,
        ?int $excludeProjectId = null,
        bool $notify = true,
    ): Project {
        return DB::transaction(function () use ($assessment, $user, $excludeProjectId, $notify) {
            $assessment = ProjectAssessment::query()
                ->whereKey($assessment->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $assessment->activeMembershipFor((int) $user->user_id);
            if ($existing) {
                throw ValidationException::withMessages([
                    'project' => [__('projects.already_assigned')],
                ]);
            }

            $project = $this->pickProject($assessment, $excludeProjectId);
            $wasFirst = $project->activeMemberCount() === 0;

            ProjectMembership::create([
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'user_id' => $user->user_id,
                'status' => ProjectMembership::STATUS_ACTIVE,
                'assigned_at' => now(),
            ]);

            $project = $project->fresh();
            $justCompleted = $this->syncTeamStatus($project, $assessment);

            if ($notify) {
                $this->notifications->notifyAssigned($user, $project->fresh(['assessment', 'activeMemberships.user']), $wasFirst);
                if (! $wasFirst) {
                    $this->notifications->notifyTeammatesOfJoin($project->fresh(['assessment', 'activeMemberships.user']), $user);
                }
                if ($justCompleted) {
                    $this->notifications->notifyTeamCompleted($project->fresh(['assessment', 'activeMemberships.user']));
                }
            }

            return $project->fresh(['assessment', 'phases', 'deliverables', 'activeMemberships.user']);
        });
    }

    public function requestChange(ProjectAssessment $assessment, User $user, string $reason): ProjectChangeRequest
    {
        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        if (! $membership) {
            throw ValidationException::withMessages([
                'project' => [__('projects.not_assigned')],
            ]);
        }

        if ($assessment->hasUsedChangeChance((int) $user->user_id)) {
            throw ValidationException::withMessages([
                'reason' => [__('projects.change_chance_used')],
            ]);
        }

        if ($assessment->pendingChangeRequestFor((int) $user->user_id)) {
            throw ValidationException::withMessages([
                'reason' => [__('projects.change_already_pending')],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => [__('projects.change_reason_required')],
            ]);
        }

        $request = ProjectChangeRequest::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'user_id' => $user->user_id,
            'from_project_id' => $membership->project_id,
            'reason' => $reason,
            'status' => ProjectChangeRequest::STATUS_PENDING,
        ]);

        $this->notifications->notifyChangeRequested($request->fresh(['user', 'assessment', 'fromProject']));

        return $request;
    }

    public function approveChange(ProjectChangeRequest $request, User $reviewer): Project
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $request = ProjectChangeRequest::query()
                ->whereKey($request->project_change_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'change' => [__('projects.change_not_pending')],
                ]);
            }

            $assessment = ProjectAssessment::query()
                ->whereKey($request->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $membership = $assessment->activeMembershipFor((int) $request->user_id);
            if (! $membership || (int) $membership->project_id !== (int) $request->from_project_id) {
                throw ValidationException::withMessages([
                    'change' => [__('projects.not_assigned')],
                ]);
            }

            $this->leaveTeam($membership, $assessment);
            $student = User::query()->whereKey($request->user_id)->firstOrFail();

            try {
                $project = $this->assignStudent($assessment, $student, (int) $request->from_project_id, notify: true);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'change' => [__('projects.no_other_team')],
                ]);
            }

            $request->update([
                'status' => ProjectChangeRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewer->user_id,
                'reviewed_at' => now(),
            ]);

            AuditLogService::recordEvent('project.change_approved', [
                'project_change_request_id' => $request->project_change_request_id,
                'user_id' => $request->user_id,
                'from_project_id' => $request->from_project_id,
                'to_project_id' => $project->project_id,
            ]);

            $this->notifications->notifyChangeDecided($request->fresh(['user', 'assessment']), approved: true, project: $project);

            return $project;
        });
    }

    public function rejectChange(ProjectChangeRequest $request, User $reviewer, ?string $adminNotes = null): ProjectChangeRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'change' => [__('projects.change_not_pending')],
            ]);
        }

        $request->update([
            'status' => ProjectChangeRequest::STATUS_REJECTED,
            'admin_notes' => $adminNotes,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
        ]);

        AuditLogService::recordEvent('project.change_rejected', [
            'project_change_request_id' => $request->project_change_request_id,
            'user_id' => $request->user_id,
            'from_project_id' => $request->from_project_id,
        ]);

        $this->notifications->notifyChangeDecided($request->fresh(['user', 'assessment']), approved: false);

        return $request->fresh();
    }

    /**
     * Pack-fill: finish started teams up to max before opening an empty team.
     * Among started teams, prefer the fullest; ties are random.
     */
    public function pickProject(ProjectAssessment $assessment, ?int $excludeProjectId = null): Project
    {
        $projects = $this->lockOpenProjects($assessment, $excludeProjectId);
        $max = (int) $assessment->max_team_size;

        $open = $projects->filter(fn (Project $project) => (int) $project->active_count < $max);
        if ($open->isEmpty()) {
            throw ValidationException::withMessages([
                'project' => [__('projects.no_seats')],
            ]);
        }

        $started = $open->filter(fn (Project $project) => (int) $project->active_count > 0);
        $pool = $started->isNotEmpty() ? $started : $open;
        $highest = (int) $pool->max('active_count');
        $candidates = $pool->filter(fn (Project $project) => (int) $project->active_count === $highest)->values();

        return $candidates->random();
    }

    public function leaveTeam(ProjectMembership $membership, ?ProjectAssessment $assessment = null): void
    {
        $membership->update([
            'status' => ProjectMembership::STATUS_LEFT,
            'left_at' => now(),
        ]);

        $project = Project::query()->whereKey($membership->project_id)->first();
        if ($project) {
            $this->syncTeamStatus($project, $assessment ?? $project->assessment);
        }
    }

    /**
     * @return Collection<int, Project>
     */
    private function lockOpenProjects(ProjectAssessment $assessment, ?int $excludeProjectId): Collection
    {
        $query = Project::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->when($excludeProjectId, fn ($q) => $q->where('project_id', '!=', $excludeProjectId))
            ->lockForUpdate()
            ->withCount(['memberships as active_count' => fn ($q) => $q->where('status', ProjectMembership::STATUS_ACTIVE)]);

        return $query->get();
    }

    private function syncTeamStatus(Project $project, ?ProjectAssessment $assessment = null): bool
    {
        $assessment ??= $project->assessment;
        $count = $project->activeMemberCount();
        $max = (int) ($assessment?->max_team_size ?? 0);
        $shouldClose = $max > 0 && $count >= $max;
        $newStatus = $shouldClose ? Project::STATUS_CLOSED : Project::STATUS_OPEN;

        if ($project->status !== $newStatus) {
            $project->update(['status' => $newStatus]);
        }

        return $shouldClose && $count === $max;
    }
}
