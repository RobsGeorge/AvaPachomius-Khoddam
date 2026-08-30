<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use App\Services\ProjectPeerEvaluationService;
use App\Services\ProjectResultsVisibilityService;
use App\Services\ProjectSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Student-facing read/write surface for team projects. Thin wrappers over the
 * same services the web controllers use, so the join window, seating and
 * submission rules stay in one place.
 */
class ProjectController extends Controller
{
    use AuthorizesStudentCourse;

    public function __construct(
        private ProjectAssignmentService $assignments,
        private ProjectSubmissionService $submissions,
        private ProjectGradingService $grading,
        private ProjectResultsVisibilityService $visibility,
        private ProjectPeerEvaluationService $peerEval,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'project.view');

        $assessments = ProjectAssessment::query()
            ->with(['module', 'projects.activeMemberships'])
            ->where('course_id', $course->course_id)
            ->where('is_published', true)
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $assessments
                ->map(fn (ProjectAssessment $assessment) => $this->serializeAssessment($assessment, $user))
                ->values(),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project->load([
            'assessment.module',
            'phases',
            'deliverables',
            'activeMemberships.user',
            'membershipEvents.user',
        ]);
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $course = $this->courseFor($assessment);
        $this->authorizeCoursePermission($user, $course, 'project.view');

        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        $isMember = $membership && (int) $membership->project_id === (int) $project->project_id;
        abort_unless($isMember, 403);

        $gradeVisibility = $this->gradeVisibilityFor($user, $assessment);

        return response()->json([
            'data' => [
                'project_id' => (int) $project->project_id,
                'title' => $project->title,
                'requirements' => $project->requirements,
                'status' => $project->status,
                'is_locked' => (bool) $project->is_locked,
                'below_minimum' => (bool) $project->below_minimum,
                'is_cancelled' => $project->isCancelled(),
                'remaining_seats' => $project->remainingSeats($assessment),
                'team_workspace_url' => $project->team_workspace_url,
                'workspace_provider' => $project->workspaceProvider(),
                'team_announcement' => $project->team_announcement,
                'assessment' => $this->serializeAssessment($assessment, $user),
                'phases' => $project->phases->map(fn ($phase) => [
                    'project_phase_id' => (int) $phase->project_phase_id,
                    'title' => $phase->title,
                    'description' => $phase->description,
                    'deadline' => $phase->deadline?->toIso8601String(),
                ])->values(),
                'deliverables' => $this->serializeChecklist($project),
                'progress' => $this->submissions->progress($project),
                'members' => $project->activeMemberships
                    ->map(fn (ProjectMembership $m) => $this->serializeMember($m, $user))
                    ->values(),
                'membership_history' => $project->membershipEvents
                    ->map(fn ($event) => [
                        'project_membership_event_id' => (int) $event->project_membership_event_id,
                        'event' => $event->event,
                        'user_id' => (int) $event->user_id,
                        'display_name' => $event->user?->displayName(),
                        'actor_user_id' => $event->actor_user_id ? (int) $event->actor_user_id : null,
                        'meta' => $event->meta,
                        'occurred_at' => $event->occurred_at?->toIso8601String(),
                    ])
                    ->values(),
                'grade' => $this->serializeGrade($gradeVisibility),
                'rubric' => ($gradeVisibility['can_view'] ?? false)
                    ? $this->grading->criterionBreakdown($assessment, $project)
                    : [],
            ],
        ]);
    }

    public function join(Request $request, ProjectAssessment $projectAssessment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $this->courseFor($projectAssessment), 'project.join');
        abort_unless($projectAssessment->is_published, 404);

        $project = $this->assignments->assignStudent($projectAssessment, $user);

        return response()->json([
            'data' => [
                'project_id' => (int) $project->project_id,
                'title' => $project->title,
                'remaining_seats' => $project->fresh()->remainingSeats($projectAssessment),
            ],
        ], 201);
    }

    public function leave(Request $request, ProjectAssessment $projectAssessment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $this->courseFor($projectAssessment), 'project.join');
        abort_unless($projectAssessment->is_published, 404);

        $project = $this->assignments->leaveAndReassign($projectAssessment, $user);

        return response()->json([
            'data' => [
                'project_id' => (int) $project->project_id,
                'title' => $project->title,
                'change_chance_used' => true,
            ],
        ]);
    }

    public function submit(Request $request, Project $project, ProjectDeliverable $deliverable): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        abort_unless((int) $deliverable->project_id === (int) $project->project_id, 404);

        $this->authorizeCoursePermission($user, $this->courseFor($assessment), 'project.join');
        $this->assertMember($assessment, $project, $user);

        $rules = [
            'body' => 'nullable|string|max:20000',
            'link_url' => 'nullable|string|max:2048',
            'replace_files' => 'nullable|boolean',
        ];

        if ($deliverable->expectsFiles()) {
            $extensions = implode(',', ProjectDeliverable::extensionsFor($deliverable->type()));
            $rules['files'] = 'nullable|array|max:'.$deliverable->maxFiles();
            $rules['files.*'] = 'file|mimes:'.$extensions.'|max:'.ProjectDeliverable::MAX_UPLOAD_KB;
        }

        $validated = $request->validate($rules);

        $submission = $this->submissions->submit(
            $project,
            $deliverable,
            $user,
            [
                'body' => $validated['body'] ?? null,
                'link_url' => $validated['link_url'] ?? null,
                'replace_files' => (bool) ($validated['replace_files'] ?? false),
            ],
            $request->file('files') ?? [],
        );

        return response()->json(['data' => $this->serializeSubmission($submission)], 201);
    }

    public function pendingPeerRatings(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $this->authorizeCoursePermission($user, $this->courseFor($assessment), 'project.join');
        $this->assertMember($assessment, $project, $user);

        $open = $this->peerEval->isOpen($assessment);
        $eligible = $open ? $this->peerEval->eligibleRateeTeams($assessment, $user) : collect();
        $existing = $open ? $this->peerEval->ratingsByRater($assessment, $user) : collect();
        $progress = $open ? $this->peerEval->progress($assessment, $user) : null;

        return response()->json([
            'data' => [
                'open' => $open,
                'scale_max' => (int) ($assessment->peer_eval_scale_max ?: 5),
                'prompt' => $assessment->peer_eval_prompt,
                'progress' => $progress,
                'eligible' => $eligible->map(function (Project $team) use ($existing) {
                    $rating = $existing->get((int) $team->project_id);

                    return [
                        'project_id' => (int) $team->project_id,
                        'title' => $team->title,
                        'rated' => $rating !== null,
                        'score' => $rating?->score,
                        'comment' => $rating?->comment,
                    ];
                })->values(),
            ],
        ]);
    }

    public function storePeerRatings(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $this->authorizeCoursePermission($user, $this->courseFor($assessment), 'project.join');
        $this->assertMember($assessment, $project, $user);

        $validated = $request->validate([
            'ratings' => 'required|array|min:1',
            'ratings.*.ratee_project_id' => 'required|integer',
            'ratings.*.score' => 'required|integer|min:1|max:10',
            'ratings.*.comment' => 'nullable|string|max:2000',
        ]);

        $saved = $this->peerEval->submitTeamRatings($assessment, $user, $validated['ratings']);

        return response()->json([
            'data' => [
                'saved' => $saved->count(),
                'progress' => $this->peerEval->progress($assessment, $user),
            ],
        ], 201);
    }

    public function destroySubmissionFile(
        Request $request,
        Project $project,
        ProjectSubmissionFile $file,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $submission = $file->submission;
        abort_unless($submission && (int) $submission->project_id === (int) $project->project_id, 404);

        $this->authorizeCoursePermission($user, $this->courseFor($assessment), 'project.join');
        $this->assertMember($assessment, $project, $user);
        abort_unless($submission->deliverable?->acceptsSubmissionNow(), 422, __('projects.submission_closed'));

        $this->submissions->deleteFile($file, $user);

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function courseFor(ProjectAssessment $assessment): Course
    {
        abort_unless($assessment->course_id, 404);

        return Course::findOrFail($assessment->course_id);
    }

    private function assertMember(ProjectAssessment $assessment, Project $project, User $user): void
    {
        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        abort_unless($membership && (int) $membership->project_id === (int) $project->project_id, 403);
    }

    /** @return array<string, mixed> */
    private function serializeAssessment(ProjectAssessment $assessment, User $user): array
    {
        $membership = $assessment->activeMembershipFor((int) $user->user_id);

        return [
            'project_assessment_id' => (int) $assessment->project_assessment_id,
            'course_id' => (int) $assessment->course_id,
            'module_id' => $assessment->module_id ? (int) $assessment->module_id : null,
            'module_title' => $assessment->module?->title,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'min_team_size' => (int) $assessment->min_team_size,
            'max_team_size' => (int) $assessment->max_team_size,
            'max_points' => $this->grading->maxPoints($assessment),
            'passing_percent' => (int) $assessment->passing_percent,
            'join_closes_at' => $assessment->join_closes_at?->toIso8601String(),
            'join_window_open' => $assessment->isJoinWindowOpen(),
            'results_announced' => $assessment->areResultsAnnounced(),
            'can_join' => $membership === null && $assessment->isJoinWindowOpen(),
            'can_leave' => $membership !== null
                && $assessment->isJoinWindowOpen()
                && ! $assessment->hasUsedChangeChance((int) $user->user_id),
            'my_project_id' => $membership ? (int) $membership->project_id : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function serializeChecklist(Project $project): array
    {
        $rows = [];
        foreach ($this->submissions->checklist($project) as $row) {
            /** @var ProjectDeliverable $deliverable */
            $deliverable = $row['deliverable'];

            $rows[] = [
                'project_deliverable_id' => (int) $deliverable->project_deliverable_id,
                'title' => $deliverable->title,
                'description' => $deliverable->description,
                'instructions' => $deliverable->instructions,
                'due_at' => $deliverable->due_at?->toIso8601String(),
                'submission_type' => $deliverable->type(),
                'file_mode' => $deliverable->allowsMultipleFiles()
                    ? ProjectDeliverable::FILE_MODE_MULTI
                    : ProjectDeliverable::FILE_MODE_SINGLE,
                'is_required' => (bool) $deliverable->is_required,
                'allow_late' => (bool) ($deliverable->allow_late ?? true),
                'max_files' => $deliverable->maxFiles(),
                'max_upload_kb' => ProjectDeliverable::MAX_UPLOAD_KB,
                'accepted_extensions' => $deliverable->expectsFiles()
                    ? ProjectDeliverable::extensionsFor($deliverable->type())
                    : [],
                'submitted' => $row['submitted'],
                'late' => $row['late'],
                'overdue' => $row['overdue'],
                'open' => $row['open'],
                'submission' => $row['submission'] ? $this->serializeSubmission($row['submission']) : null,
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function serializeSubmission(ProjectDeliverableSubmission $submission): array
    {
        $submission->loadMissing(['files', 'submitter']);

        return [
            'project_deliverable_submission_id' => (int) $submission->project_deliverable_submission_id,
            'project_deliverable_id' => (int) $submission->project_deliverable_id,
            'body' => $submission->body,
            'link_url' => $submission->link_url,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'is_late' => (bool) $submission->is_late,
            'submitted_by' => $submission->submitter?->displayName(),
            'files' => $submission->files->map(fn (ProjectSubmissionFile $file) => [
                'project_submission_file_id' => (int) $file->project_submission_file_id,
                'name' => $file->displayName(),
                'url' => $file->url(),
                'mime_type' => $file->mime_type,
                'size_bytes' => (int) $file->size_bytes,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMember(ProjectMembership $membership, User $viewer): array
    {
        $member = $membership->user;
        $isSelf = $member && (int) $member->user_id === (int) $viewer->user_id;

        return [
            'user_id' => $member ? (int) $member->user_id : null,
            'display_name' => $member?->displayName(),
            'photo_url' => $member?->profile_photo ? Storage::disk('public')->url($member->profile_photo) : null,
            // Teammates share contact details so they can organise the work.
            'mobile_number' => $member?->mobile_number,
            'email' => $member?->email,
            'is_self' => (bool) $isSelf,
            'joined_at' => $membership->assigned_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{can_view:bool, reason:string, grade:mixed, passed:?bool}  $visibility
     * @return array<string, mixed>
     */
    private function serializeGrade(array $visibility): array
    {
        if (! $visibility['can_view']) {
            return [
                'can_view' => false,
                'reason' => $visibility['reason'],
            ];
        }

        $grade = $visibility['grade'];

        return [
            'can_view' => true,
            'reason' => $visibility['reason'],
            'points' => $grade ? round((float) $grade->points, 2) : null,
            'percent' => $grade ? round((float) $grade->percent, 2) : null,
            'source' => $grade?->source,
            'passed' => $visibility['passed'],
        ];
    }

    /**
     * @return array{can_view:bool, reason:string, grade:mixed, passed:?bool}
     */
    private function gradeVisibilityFor(User $user, ProjectAssessment $assessment): array
    {
        $grade = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $user->user_id)
            ->first();

        return [
            'can_view' => $this->visibility->canStudentViewScore($user, $assessment),
            'reason' => $this->visibility->hideReason($user, $assessment),
            'grade' => $grade,
            'passed' => $grade ? $grade->passed((int) $assessment->passing_percent) : null,
        ];
    }
}
