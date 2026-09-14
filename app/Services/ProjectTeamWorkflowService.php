<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMemberVerification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectTeamWorkflowService
{
    public function __construct(
        private ProjectSubmissionService $submissions,
    ) {}

    /**
     * @return list<array{slot_key:string, title:string, description:string, instructions:string}>
     */
    public static function canonicalSlots(): array
    {
        return [
            [
                'slot_key' => ProjectDeliverable::SLOT_ANNOUNCEMENT,
                'title' => __('projects.slot_announcement_title'),
                'description' => __('projects.slot_announcement_help'),
                'instructions' => __('projects.slot_announcement_instructions'),
            ],
            [
                'slot_key' => ProjectDeliverable::SLOT_MAIN_CONTENT,
                'title' => __('projects.slot_main_content_title'),
                'description' => __('projects.slot_main_content_help'),
                'instructions' => __('projects.slot_main_content_instructions'),
            ],
            [
                'slot_key' => ProjectDeliverable::SLOT_FEEDBACK,
                'title' => __('projects.slot_feedback_title'),
                'description' => __('projects.slot_feedback_help'),
                'instructions' => __('projects.slot_feedback_instructions'),
            ],
        ];
    }

    /**
     * @return list<array{title:string, description:string, submission_type:string, is_required:bool, allow_late:bool, slot_key:string, due_at:?string}>
     */
    public static function canonicalDeliverablePayload(?string $dueAt): array
    {
        $rows = [];
        foreach (self::canonicalSlots() as $slot) {
            $rows[] = [
                'title' => $slot['title'],
                'description' => $slot['description'],
                'instructions' => $slot['instructions'],
                'submission_type' => ProjectDeliverable::TYPE_LINK,
                'is_required' => true,
                'allow_late' => true,
                'slot_key' => $slot['slot_key'],
                'due_at' => $dueAt,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{title:string, description:string, deadline:?string}>
     */
    public static function canonicalPhasePayload(?string $dueAt): array
    {
        $rows = [];
        foreach (self::canonicalSlots() as $slot) {
            $rows[] = [
                'title' => $slot['title'],
                'description' => $slot['description'],
                'deadline' => $dueAt,
            ];
        }

        return $rows;
    }

    public function verify(Project $project, User $user): ProjectMemberVerification
    {
        $this->assertMember($project, $user);
        $this->assertWindowOpen($project);

        $progress = $this->submissions->progress($project);
        if (($progress['missing'] ?? 1) > 0) {
            throw ValidationException::withMessages([
                'verify' => [__('projects.verify_need_items')],
            ]);
        }

        $row = ProjectMemberVerification::updateOrCreate(
            [
                'project_id' => $project->project_id,
                'user_id' => $user->user_id,
            ],
            [
                'project_assessment_id' => $project->project_assessment_id,
                'verified_at' => now(),
            ]
        );

        AuditLogService::recordEvent('project.member_verified', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'user_id' => $user->user_id,
        ]);

        return $row;
    }

    public function unverify(Project $project, User $user): void
    {
        $this->assertMember($project, $user);
        ProjectMemberVerification::query()
            ->where('project_id', $project->project_id)
            ->where('user_id', $user->user_id)
            ->delete();

        $this->clearFinalSubmit($project);
    }

    public function finalSubmit(Project $project, User $user): Project
    {
        $this->assertMember($project, $user);
        $this->assertWindowOpen($project);

        $progress = $this->submissions->progress($project);
        if (($progress['missing'] ?? 1) > 0) {
            throw ValidationException::withMessages([
                'final' => [__('projects.final_need_items')],
            ]);
        }

        $pending = $this->unverifiedMembers($project);
        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'final' => [__('projects.final_need_verifications', [
                    'names' => $pending->map(fn (User $member) => $member->displayName())->implode('، '),
                ])],
            ]);
        }

        $project->update([
            'final_submitted_at' => now(),
            'final_submitted_by_user_id' => $user->user_id,
        ]);

        AuditLogService::recordEvent('project.final_submitted', [
            'project_id' => $project->project_id,
            'project_assessment_id' => $project->project_assessment_id,
            'user_id' => $user->user_id,
            'late' => $project->fresh()->isLateFinal(),
        ]);

        return $project->fresh(['finalSubmitter', 'activeMemberships.user']);
    }

    public function resetAfterContentChange(Project $project): void
    {
        ProjectMemberVerification::query()->where('project_id', $project->project_id)->delete();
        $this->clearFinalSubmit($project);
    }

    public function resetForProject(Project $project): void
    {
        $this->resetAfterContentChange($project);
    }

    /**
     * @return Collection<int, User>
     */
    public function unverifiedMembers(Project $project): Collection
    {
        $verifiedIds = ProjectMemberVerification::query()
            ->where('project_id', $project->project_id)
            ->pluck('user_id')
            ->all();

        return $project->activeMembers()
            ->reject(fn (User $member) => in_array((int) $member->user_id, array_map('intval', $verifiedIds), true))
            ->values();
    }

    /**
     * @return Collection<int, ProjectMemberVerification>
     */
    public function verifications(Project $project): Collection
    {
        return ProjectMemberVerification::query()
            ->where('project_id', $project->project_id)
            ->with('user')
            ->orderBy('verified_at')
            ->get();
    }

    public function memberHasVerified(Project $project, User $user): bool
    {
        return ProjectMemberVerification::query()
            ->where('project_id', $project->project_id)
            ->where('user_id', $user->user_id)
            ->exists();
    }

    public function settleRoster(ProjectAssessment $assessment, User $admin): ProjectAssessment
    {
        if ($assessment->isJoinWindowOpen()) {
            throw ValidationException::withMessages([
                'settle' => [__('projects.settle_before_join_close')],
            ]);
        }

        if ($assessment->areTeamsSettled()) {
            throw ValidationException::withMessages([
                'settle' => [__('projects.roster_already_settled')],
            ]);
        }

        $assessment->update([
            'teams_settled_at' => now(),
            'teams_settled_by_user_id' => $admin->user_id,
        ]);

        AuditLogService::recordEvent('project.teams_settled', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'user_id' => $admin->user_id,
        ]);

        app(ProjectNotificationService::class)->notifyRosterApproved($assessment->fresh([
            'projects.activeMemberships.user',
            'course',
        ]));

        return $assessment->fresh();
    }

    public function notifyJoinClosedIfNeeded(ProjectAssessment $assessment): void
    {
        if ($assessment->isJoinWindowOpen() || $assessment->join_close_admin_notified_at) {
            return;
        }

        if (! $assessment->is_published) {
            return;
        }

        app(ProjectNotificationService::class)->notifyJoinWindowClosed($assessment);
        $assessment->update(['join_close_admin_notified_at' => now()]);
    }

    private function assertMember(Project $project, User $user): void
    {
        $membership = $project->assessment?->activeMembershipFor((int) $user->user_id);
        if (! $membership || (int) $membership->project_id !== (int) $project->project_id) {
            abort(403);
        }
    }

    private function assertWindowOpen(Project $project): void
    {
        $assessment = $project->assessment;
        if ($assessment && ! $assessment->acceptsSubmissionsNow()) {
            throw ValidationException::withMessages([
                'final' => [__('projects.submission_hard_closed')],
            ]);
        }
    }

    private function clearFinalSubmit(Project $project): void
    {
        if ($project->final_submitted_at === null && $project->final_submitted_by_user_id === null) {
            return;
        }

        $project->update([
            'final_submitted_at' => null,
            'final_submitted_by_user_id' => null,
        ]);
    }
}
