<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectMembership;
use App\Models\ProjectMembershipEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAssignmentService
{
    public function __construct(
        private ProjectNotificationService $notifications,
        private StudentRosterService $roster,
    ) {}

    public function assignStudent(
        ProjectAssessment $assessment,
        User $user,
        ?int $excludeProjectId = null,
        bool $notify = true,
        bool $enforceJoinWindow = true,
    ): Project {
        return DB::transaction(function () use ($assessment, $user, $excludeProjectId, $notify, $enforceJoinWindow) {
            $assessment = ProjectAssessment::query()
                ->whereKey($assessment->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($enforceJoinWindow) {
                $this->assertJoinWindowOpen($assessment);
            }

            $existing = $assessment->activeMembershipFor((int) $user->user_id);
            if ($existing) {
                throw ValidationException::withMessages([
                    'project' => [__('projects.already_assigned')],
                ]);
            }

            $project = $this->pickProject($assessment, $excludeProjectId);
            $wasFirst = $project->activeMemberCount() === 0;

            $this->seatMember($assessment, $project, (int) $user->user_id);
            $this->recordMembershipEvent(
                $assessment,
                $project,
                (int) $user->user_id,
                ProjectMembershipEvent::EVENT_JOINED,
            );

            $project = $project->fresh();
            $justCompleted = $this->syncTeamStatus($project, $assessment);
            app(ProjectGradingService::class)->inheritTeamGradeIfNeeded($assessment, $project, $user);

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

    /**
     * Projects v2 self-service: one leave per student per assessment, followed by an
     * immediate random reassignment that excludes the team they just left.
     */
    public function leaveAndReassign(ProjectAssessment $assessment, User $user): Project
    {
        return DB::transaction(function () use ($assessment, $user) {
            $assessment = ProjectAssessment::query()
                ->whereKey($assessment->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertJoinWindowOpen($assessment);

            $membership = $assessment->activeMembershipFor((int) $user->user_id);
            if (! $membership) {
                throw ValidationException::withMessages([
                    'project' => [__('projects.not_assigned')],
                ]);
            }

            if ($assessment->hasUsedChangeChance((int) $user->user_id)) {
                throw ValidationException::withMessages([
                    'project' => [__('projects.change_chance_used')],
                ]);
            }

            $fromProjectId = (int) $membership->project_id;
            $fromProject = Project::query()->whereKey($fromProjectId)->first();

            $this->leaveTeam($membership, $assessment, chanceUsed: true);

            try {
                $project = $this->assignStudent(
                    $assessment,
                    $user,
                    excludeProjectId: $fromProjectId,
                    notify: true,
                    enforceJoinWindow: false,
                );
            } catch (ValidationException) {
                throw ValidationException::withMessages([
                    'project' => [__('projects.no_other_team_self')],
                ]);
            }

            AuditLogService::recordEvent('project.member_self_left', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'user_id' => $user->user_id,
                'from_project_id' => $fromProjectId,
                'to_project_id' => $project->project_id,
            ]);

            if ($fromProject) {
                $this->notifications->notifyMemberLeft($fromProject->fresh(['assessment', 'activeMemberships.user']), $user);
            }

            return $project;
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
                $project = $this->assignStudent(
                    $assessment,
                    $student,
                    excludeProjectId: (int) $request->from_project_id,
                    notify: true,
                    enforceJoinWindow: false,
                );
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
     * Admin force-move. Bypasses the join window and the student's change chance;
     * the target team must still have a free seat and not be cancelled.
     */
    public function moveMember(
        ProjectMembership $membership,
        Project $target,
        User $actor,
        string $inboundEvent = ProjectMembershipEvent::EVENT_MOVED_IN,
    ): ProjectMembership
    {
        return DB::transaction(function () use ($membership, $target, $actor, $inboundEvent) {
            $assessment = ProjectAssessment::query()
                ->whereKey($membership->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $target->project_assessment_id !== (int) $assessment->project_assessment_id) {
                abort(404);
            }

            if (! $membership->isActive()) {
                throw ValidationException::withMessages([
                    'membership' => [__('projects.not_assigned')],
                ]);
            }

            $fromProjectId = (int) $membership->project_id;
            if ($fromProjectId === (int) $target->project_id) {
                throw ValidationException::withMessages([
                    'to_project_id' => [__('projects.move_same_team')],
                ]);
            }

            $target = Project::query()->whereKey($target->project_id)->lockForUpdate()->firstOrFail();
            if ($target->isCancelled()) {
                throw ValidationException::withMessages([
                    'to_project_id' => [__('projects.team_cancelled')],
                ]);
            }
            if ($target->remainingSeats($assessment) < 1) {
                throw ValidationException::withMessages([
                    'to_project_id' => [__('projects.no_seats_in_team')],
                ]);
            }

            $student = User::query()->whereKey($membership->user_id)->firstOrFail();
            $fromProject = Project::query()->whereKey($fromProjectId)->first();

            $membership->update([
                'status' => ProjectMembership::STATUS_LEFT,
                'left_at' => now(),
                'moved_by_user_id' => $actor->user_id,
            ]);

            if ($fromProject) {
                $this->recordMembershipEvent(
                    $assessment,
                    $fromProject,
                    (int) $student->user_id,
                    ProjectMembershipEvent::EVENT_MOVED_OUT,
                    (int) $actor->user_id,
                    ['to_project_id' => (int) $target->project_id],
                );
            }

            $moved = $this->seatMember($assessment, $target, (int) $student->user_id, (int) $actor->user_id);
            $this->recordMembershipEvent(
                $assessment,
                $target,
                (int) $student->user_id,
                $inboundEvent,
                (int) $actor->user_id,
                ['from_project_id' => $fromProjectId],
            );

            if ($fromProject) {
                $this->syncTeamStatus($fromProject->fresh(), $assessment);
            }
            $target = $target->fresh();
            $justCompleted = $this->syncTeamStatus($target, $assessment);
            app(ProjectGradingService::class)->inheritTeamGradeIfNeeded($assessment, $target, $student);

            AuditLogService::recordEvent('project.member_moved', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'user_id' => $student->user_id,
                'from_project_id' => $fromProjectId,
                'to_project_id' => $target->project_id,
                'actor_user_id' => $actor->user_id,
            ]);

            $this->notifications->notifyMoved($student, $target->fresh(['assessment', 'activeMemberships.user']));
            if ($fromProject) {
                $this->notifications->notifyMemberLeft($fromProject->fresh(['assessment', 'activeMemberships.user']), $student);
            }
            if ($justCompleted) {
                $this->notifications->notifyTeamCompleted($target->fresh(['assessment', 'activeMemberships.user']));
            }

            return $moved->fresh();
        });
    }

    /**
     * Locked teams keep their roster but are skipped by pack-fill, so an admin can
     * close a team below max (e.g. after the join window shut).
     */
    public function lockTeam(Project $project, User $actor, bool $locked = true): Project
    {
        $project->update(['is_locked' => $locked]);

        AuditLogService::recordEvent($locked ? 'project.team_locked' : 'project.team_unlocked', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $project->fresh();
    }

    public function cancelTeam(Project $project, User $actor): Project
    {
        if ($project->activeMemberCount() > 0) {
            throw ValidationException::withMessages([
                'project' => [__('projects.cancel_needs_empty_team')],
            ]);
        }

        $project->update([
            'cancelled_at' => now(),
            'below_minimum' => false,
            'status' => Project::STATUS_CLOSED,
        ]);

        AuditLogService::recordEvent('project.team_cancelled', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $project->fresh();
    }

    public function restoreTeam(Project $project, User $actor): Project
    {
        $project->update(['cancelled_at' => null]);
        $this->syncTeamStatus($project->fresh(), $project->assessment);

        AuditLogService::recordEvent('project.team_restored', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $project->fresh();
    }

    /**
     * Rescue path for under-minimum teams: move every active member of $from into
     * $into, then cancel the emptied team.
     *
     * @return array{moved:int, into:Project, from:Project}
     */
    public function mergeTeams(Project $from, Project $into, User $actor): array
    {
        if ((int) $from->project_id === (int) $into->project_id) {
            throw ValidationException::withMessages([
                'into_project_id' => [__('projects.move_same_team')],
            ]);
        }

        if ((int) $from->project_assessment_id !== (int) $into->project_assessment_id) {
            abort(404);
        }

        return DB::transaction(function () use ($from, $into, $actor) {
            $assessment = ProjectAssessment::query()
                ->whereKey($from->project_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $memberships = $from->activeMemberships()->get();
            if ($memberships->isEmpty()) {
                throw ValidationException::withMessages([
                    'from_project_id' => [__('projects.merge_source_empty')],
                ]);
            }

            $into = Project::query()->whereKey($into->project_id)->lockForUpdate()->firstOrFail();
            if ($into->isCancelled()) {
                throw ValidationException::withMessages([
                    'into_project_id' => [__('projects.team_cancelled')],
                ]);
            }
            if ($into->remainingSeats($assessment) < $memberships->count()) {
                throw ValidationException::withMessages([
                    'into_project_id' => [__('projects.merge_target_too_small', [
                        'needed' => $memberships->count(),
                        'available' => $into->remainingSeats($assessment),
                    ])],
                ]);
            }

            foreach ($memberships as $membership) {
                $this->moveMember(
                    $membership,
                    $into,
                    $actor,
                    ProjectMembershipEvent::EVENT_MERGED_IN,
                );
            }

            $from = $from->fresh();
            $this->cancelTeam($from, $actor);

            AuditLogService::recordEvent('project.teams_merged', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'from_project_id' => $from->project_id,
                'into_project_id' => $into->project_id,
                'moved' => $memberships->count(),
                'actor_user_id' => $actor->user_id,
            ]);

            return [
                'moved' => $memberships->count(),
                'into' => $into->fresh(),
                'from' => $from->fresh(),
            ];
        });
    }

    /**
     * After the join window closes, flag every started team that never reached
     * `min_team_size` so the manage dashboard can surface merge/rescue actions.
     */
    public function markBelowMinimumAfterJoinClose(ProjectAssessment $assessment): int
    {
        if ($assessment->isJoinWindowOpen()) {
            return 0;
        }

        $flagged = 0;
        foreach ($assessment->projects()->get() as $project) {
            $below = ! $project->isCancelled() && $project->isBelowMinimum($assessment);
            if ((bool) $project->below_minimum === $below) {
                continue;
            }

            $project->update(['below_minimum' => $below]);
            if ($below) {
                $flagged++;
            }
        }

        if ($flagged > 0) {
            AuditLogService::recordEvent('project.below_minimum_flagged', [
                'project_assessment_id' => $assessment->project_assessment_id,
                'flagged' => $flagged,
            ]);
        }

        return $flagged;
    }

    /**
     * Pack-fill: finish started teams up to max before opening an empty team.
     * Among started teams, prefer the fullest; ties are random. When only empty
     * teams remain, the random pick is bounded to a seed pool sized to the number
     * of teams actually needed for the students still unassigned.
     */
    public function pickProject(ProjectAssessment $assessment, ?int $excludeProjectId = null): Project
    {
        $projects = $this->lockSeatableProjects($assessment, $excludeProjectId);
        $max = (int) $assessment->max_team_size;

        $open = $projects->filter(fn (Project $project) => (int) $project->active_count < $max);
        if ($open->isEmpty()) {
            throw ValidationException::withMessages([
                'project' => [__('projects.no_seats')],
            ]);
        }

        $started = $open->filter(fn (Project $project) => (int) $project->active_count > 0);
        if ($started->isNotEmpty()) {
            $highest = (int) $started->max('active_count');

            return $started
                ->filter(fn (Project $project) => (int) $project->active_count === $highest)
                ->values()
                ->random();
        }

        return $this->seedPool($assessment, $open)->random();
    }

    /**
     * Number of empty teams pack-fill may seed at once:
     * ceil(students still unassigned / max_team_size), or the admin override.
     */
    public function seedPoolSize(ProjectAssessment $assessment): int
    {
        if ($assessment->seed_pool_size !== null && (int) $assessment->seed_pool_size > 0) {
            return (int) $assessment->seed_pool_size;
        }

        $max = max(1, (int) $assessment->max_team_size);
        $remaining = $this->unassignedStudentCount($assessment);

        return max(1, (int) ceil($remaining / $max));
    }

    public function unassignedStudentCount(ProjectAssessment $assessment): int
    {
        $course = $assessment->course;
        if (! $course) {
            return 0;
        }

        $enrolled = $this->roster->enrolledStudents($course)->count();
        $assigned = $assessment->memberships()
            ->where('status', ProjectMembership::STATUS_ACTIVE)
            ->count();

        return max($enrolled - $assigned, 0);
    }

    public function leaveTeam(
        ProjectMembership $membership,
        ?ProjectAssessment $assessment = null,
        bool $chanceUsed = false,
    ): void {
        $assessment ??= ProjectAssessment::query()
            ->whereKey($membership->project_assessment_id)
            ->first();
        $project = Project::query()->whereKey($membership->project_id)->first();

        $membership->update(array_filter([
            'status' => ProjectMembership::STATUS_LEFT,
            'left_at' => now(),
            'change_chance_used_at' => $chanceUsed ? now() : null,
        ], fn ($value) => $value !== null));

        if ($assessment && $project) {
            $this->recordMembershipEvent(
                $assessment,
                $project,
                (int) $membership->user_id,
                ProjectMembershipEvent::EVENT_LEFT,
            );
            $this->syncTeamStatus($project, $assessment);
        } elseif ($project) {
            $this->syncTeamStatus($project, $assessment ?? $project->assessment);
        }
    }

    /**
     * Student-visible append-only timeline row for a team.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordMembershipEvent(
        ProjectAssessment $assessment,
        Project $project,
        int $userId,
        string $event,
        ?int $actorUserId = null,
        array $meta = [],
    ): ProjectMembershipEvent {
        return ProjectMembershipEvent::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'meta' => $meta === [] ? null : $meta,
            'occurred_at' => now(),
        ]);
    }

    /**
     * `project_memberships` is unique on (project_id, user_id), so a student who
     * comes back to a team they were once on must revive that row instead of
     * inserting a second one. `change_chance_used_at` is left untouched: the
     * chance is spent for the whole assessment, not per seat.
     */
    private function seatMember(
        ProjectAssessment $assessment,
        Project $project,
        int $userId,
        ?int $movedByUserId = null,
    ): ProjectMembership {
        return ProjectMembership::updateOrCreate(
            [
                'project_id' => $project->project_id,
                'user_id' => $userId,
            ],
            [
                'project_assessment_id' => $assessment->project_assessment_id,
                'status' => ProjectMembership::STATUS_ACTIVE,
                'assigned_at' => now(),
                'left_at' => null,
                'moved_by_user_id' => $movedByUserId,
            ]
        );
    }

    /**
     * @param  Collection<int, Project>  $open
     * @return Collection<int, Project>
     */
    private function seedPool(ProjectAssessment $assessment, Collection $open): Collection
    {
        $ordered = $open
            ->sortBy([
                fn (Project $a, Project $b) => (int) $a->sort_order <=> (int) $b->sort_order,
                fn (Project $a, Project $b) => (int) $a->project_id <=> (int) $b->project_id,
            ])
            ->values();

        return $ordered->take(max(1, $this->seedPoolSize($assessment)))->values();
    }

    /**
     * @return Collection<int, Project>
     */
    private function lockSeatableProjects(ProjectAssessment $assessment, ?int $excludeProjectId): Collection
    {
        return Project::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->when($excludeProjectId, fn ($q) => $q->where('project_id', '!=', $excludeProjectId))
            ->whereNull('cancelled_at')
            ->where(fn ($q) => $q->where('is_locked', false)->orWhereNull('is_locked'))
            ->lockForUpdate()
            ->withCount(['memberships as active_count' => fn ($q) => $q->where('status', ProjectMembership::STATUS_ACTIVE)])
            ->get();
    }

    private function assertJoinWindowOpen(ProjectAssessment $assessment): void
    {
        if ($assessment->hasJoinWindowClosed()) {
            throw ValidationException::withMessages([
                'project' => [__('projects.join_window_closed')],
            ]);
        }
    }

    private function syncTeamStatus(Project $project, ?ProjectAssessment $assessment = null): bool
    {
        $assessment ??= $project->assessment;
        if ($project->isCancelled()) {
            return false;
        }

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
