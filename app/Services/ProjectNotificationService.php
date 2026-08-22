<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectChangeRequest;
use App\Models\User;

class ProjectNotificationService
{
    public function __construct(
        private NotificationGeneratorService $notifications,
        private StudentRosterService $roster,
        private CoursePermissionResolver $permissions,
    ) {}

    public function notifyAssigned(User $user, Project $project, bool $wasFirst): void
    {
        $url = route('projects.show', $project);
        $title = __('projects.notify_assigned_title', ['project' => $project->title]);
        $body = $wasFirst
            ? __('projects.notify_first_member_body', ['project' => $project->title])
            : __('projects.notify_assigned_body', [
                'project' => $project->title,
                'teammates' => $this->rosterLines($project, exceptUserId: (int) $user->user_id),
            ]);

        $this->notifications->createOrUpdate(
            $user,
            'project_assigned',
            $title,
            $body,
            $url,
            Project::class,
            (int) $project->project_id,
            metadata: [
                'course_id' => $project->assessment?->course_id,
                'project_id' => $project->project_id,
                'first_member' => $wasFirst,
            ],
            dedupeKey: 'project_assigned:'.$project->project_id.':'.$user->user_id.':'.now()->timestamp,
        );
    }

    public function notifyTeammatesOfJoin(Project $project, User $newMember): void
    {
        $url = route('projects.show', $project);
        $title = __('projects.notify_teammate_joined_title', ['project' => $project->title]);
        $body = __('projects.notify_teammate_joined_body', [
            'name' => $newMember->displayName(),
            'phone' => $this->phoneLabel($newMember),
            'project' => $project->title,
        ]);

        foreach ($project->activeMembers() as $member) {
            if ((int) $member->user_id === (int) $newMember->user_id) {
                continue;
            }

            $this->notifications->createOrUpdate(
                $member,
                'project_teammate_joined',
                $title,
                $body,
                $url,
                Project::class,
                (int) $project->project_id,
                metadata: [
                    'course_id' => $project->assessment?->course_id,
                    'project_id' => $project->project_id,
                    'new_member_id' => $newMember->user_id,
                ],
                dedupeKey: 'project_teammate_joined:'.$project->project_id.':'.$member->user_id.':'.$newMember->user_id,
            );
        }
    }

    public function notifyTeamCompleted(Project $project): void
    {
        $url = route('projects.show', $project);
        $title = __('projects.notify_team_completed_title', ['project' => $project->title]);
        $body = __('projects.notify_team_completed_body', [
            'project' => $project->title,
            'members' => $this->rosterLines($project),
        ]);

        foreach ($project->activeMembers() as $member) {
            $this->notifications->createOrUpdate(
                $member,
                'project_team_completed',
                $title,
                $body,
                $url,
                Project::class,
                (int) $project->project_id,
                metadata: [
                    'course_id' => $project->assessment?->course_id,
                    'project_id' => $project->project_id,
                ],
                dedupeKey: 'project_team_completed:'.$project->project_id.':'.$member->user_id,
            );
        }
    }

    public function notifyChangeRequested(ProjectChangeRequest $request): void
    {
        $assessment = $request->assessment;
        $course = $assessment?->course;
        if (! $course) {
            return;
        }

        $url = route('projects.change-requests.index', ['assessment' => $assessment->project_assessment_id]);
        $title = __('projects.notify_change_requested_title', ['assessment' => $assessment->title]);
        $body = __('projects.notify_change_requested_body', [
            'name' => $request->user?->displayName() ?? '',
            'project' => $request->fromProject?->title ?? '',
        ]);

        foreach ($this->roster->courseStaff((string) $course->course_id) as $staff) {
            if (! $this->permissions->canInCourse($staff, 'project.manage', $course)) {
                continue;
            }

            $this->notifications->createOrUpdate(
                $staff,
                'project_change_requested',
                $title,
                $body,
                $url,
                ProjectChangeRequest::class,
                (int) $request->project_change_request_id,
                metadata: [
                    'course_id' => $course->course_id,
                    'project_change_request_id' => $request->project_change_request_id,
                ],
                dedupeKey: 'project_change_requested:'.$request->project_change_request_id.':'.$staff->user_id,
            );
        }
    }

    public function notifyChangeDecided(ProjectChangeRequest $request, bool $approved, ?Project $project = null): void
    {
        $user = $request->user;
        if (! $user) {
            return;
        }

        $url = $project
            ? route('projects.show', $project)
            : route('projects.index');

        $title = $approved
            ? __('projects.notify_change_approved_title')
            : __('projects.notify_change_rejected_title');
        $body = $approved
            ? __('projects.notify_change_approved_body', ['project' => $project?->title ?? ''])
            : __('projects.notify_change_rejected_body');

        $this->notifications->createOrUpdate(
            $user,
            'project_change_decided',
            $title,
            $body,
            $url,
            ProjectChangeRequest::class,
            (int) $request->project_change_request_id,
            metadata: [
                'course_id' => $request->assessment?->course_id,
                'approved' => $approved,
            ],
            dedupeKey: 'project_change_decided:'.$request->project_change_request_id,
        );
    }

    private function rosterLines(Project $project, ?int $exceptUserId = null): string
    {
        $lines = $project->activeMembers()
            ->filter(fn (User $user) => $exceptUserId === null || (int) $user->user_id !== $exceptUserId)
            ->map(fn (User $user) => $user->displayName().' — '.$this->phoneLabel($user))
            ->values();

        if ($lines->isEmpty()) {
            return __('projects.no_other_teammates');
        }

        return $lines->implode("\n");
    }

    private function phoneLabel(User $user): string
    {
        $mobile = trim((string) $user->mobile_number);

        return $mobile !== '' ? $mobile : __('projects.phone_missing');
    }
}
