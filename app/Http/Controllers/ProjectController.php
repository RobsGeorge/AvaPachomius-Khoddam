<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectSubmissionFile;
use App\Services\CoursePermissionResolver;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use App\Services\ProjectPeerEvaluationService;
use App\Services\ProjectResultsVisibilityService;
use App\Services\ProjectSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function __construct(
        private CoursePermissionResolver $permissions,
        private ProjectAssignmentService $assignments,
        private ProjectResultsVisibilityService $visibility,
        private ProjectSubmissionService $submissions,
        private ProjectGradingService $grading,
        private ProjectPeerEvaluationService $peerEval,
    ) {}

    public function index()
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->assertCanView();

        $course = current_course();
        $canManage = $this->userCanManage();

        $query = ProjectAssessment::with([
            'module',
            'course',
            'projects.activeMemberships.user',
        ])->orderBy('title');

        if ($course) {
            $query->where('course_id', $course->course_id);
        }

        if (! $canManage) {
            $query->where('is_published', true);
        }

        $assessments = $query->get();
        $memberships = [];
        $gradeVisibility = [];
        foreach ($assessments as $assessment) {
            $memberships[$assessment->project_assessment_id] = $assessment->activeMembershipFor((int) $user->user_id);
            $gradeVisibility[$assessment->project_assessment_id] = $this->gradeVisibilityFor($user, $assessment);
        }

        return view('projects.index', compact('assessments', 'memberships', 'canManage', 'gradeVisibility'));
    }

    public function leave(Request $request, ProjectAssessment $projectAssessment)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->assertCanJoin($projectAssessment);
        abort_unless($projectAssessment->is_published, 404);

        $project = $this->assignments->leaveAndReassign($projectAssessment, $user);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('projects.left_and_reassigned'));
    }

    public function show(Project $project)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $project->load([
            'assessment.module',
            'assessment.course',
            'phases',
            'deliverables',
            'activeMemberships.user',
            'membershipEvents.user',
        ]);
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $this->assertSameCourse($assessment);
        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        $canManage = $this->userCanManageCourse((int) $assessment->course_id);

        if (! $canManage) {
            abort_unless($membership && (int) $membership->project_id === (int) $project->project_id, 403);
            abort_unless($assessment->is_published || $membership, 404);
        }

        $pendingChange = $membership
            ? $assessment->pendingChangeRequestFor((int) $user->user_id)
            : null;
        $changeUsed = $assessment->hasUsedChangeChance((int) $user->user_id);
        $joinWindowOpen = $assessment->isJoinWindowOpen();

        $gradeVisibility = $this->gradeVisibilityFor($user, $assessment);
        $checklist = $this->submissions->checklist($project);
        $progress = $this->submissions->progress($project);
        $isMember = $membership && (int) $membership->project_id === (int) $project->project_id;
        $teamHistory = ($isMember || $canManage)
            ? $project->membershipEvents
            : collect();
        $peerEvalOpen = $this->peerEval->isOpen($assessment);
        $peerPending = ($isMember && $peerEvalOpen)
            ? $this->peerEval->pendingTeammates($project, $user)
            : collect();
        $peerAverages = $canManage
            ? $this->peerEval->adminAverages($project)
            : [];

        // The rubric breakdown follows the same gate as the numeric grade.
        $rubric = ($gradeVisibility['can_view'] ?? false) || $canManage
            ? $this->grading->criterionBreakdown($assessment, $project)
            : [];

        return view('projects.show', compact(
            'project',
            'assessment',
            'membership',
            'canManage',
            'pendingChange',
            'changeUsed',
            'joinWindowOpen',
            'gradeVisibility',
            'checklist',
            'progress',
            'isMember',
            'rubric',
            'teamHistory',
            'peerEvalOpen',
            'peerPending',
            'peerAverages'
        ));
    }

    public function submitPeerRatings(Request $request, Project $project)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        $this->assertCanJoin($assessment);

        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        abort_unless($membership && (int) $membership->project_id === (int) $project->project_id, 403);

        $validated = $request->validate([
            'ratings' => 'required|array|min:1',
            'ratings.*.ratee_user_id' => 'required|integer',
            'ratings.*.score' => 'required|integer|min:1|max:10',
            'ratings.*.comment' => 'nullable|string|max:2000',
        ]);

        $this->peerEval->submitRatings($project, $user, $validated['ratings']);

        return back()->with('success', __('projects.peer_eval_saved'));
    }

    public function submitDeliverable(Request $request, Project $project, ProjectDeliverable $deliverable)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        abort_unless((int) $deliverable->project_id === (int) $project->project_id, 404);

        $this->assertCanJoin($assessment);
        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        abort_unless($membership && (int) $membership->project_id === (int) $project->project_id, 403);

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

        $this->submissions->submit(
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

        return back()->with('success', __('projects.submission_saved'));
    }

    public function destroySubmissionFile(Project $project, ProjectSubmissionFile $file)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);

        $submission = $file->submission;
        abort_unless($submission && (int) $submission->project_id === (int) $project->project_id, 404);

        $this->assertCanJoin($assessment);
        $membership = $assessment->activeMembershipFor((int) $user->user_id);
        abort_unless($membership && (int) $membership->project_id === (int) $project->project_id, 403);
        abort_unless($submission->deliverable?->acceptsSubmissionNow(), 422, __('projects.submission_closed'));

        $this->submissions->deleteFile($file, $user);

        return back()->with('success', __('projects.submission_file_deleted'));
    }

    public function join(Request $request, ProjectAssessment $projectAssessment)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->assertCanJoin($projectAssessment);
        abort_unless($projectAssessment->is_published, 404);

        $project = $this->assignments->assignStudent($projectAssessment, $user);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('projects.assigned_success'));
    }

    /**
     * Retired in Projects v2: students now leave-and-reassign themselves once
     * (`projects.leave`). The route and history table stay so old links and the
     * admin review screen keep working for requests filed under v1.
     */
    public function storeChangeRequest(Request $request, ProjectAssessment $projectAssessment)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->assertCanJoin($projectAssessment);

        return back()->with('error', __('projects.change_request_retired'));
    }

    private function assertCanView(): void
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return;
        }

        $course = current_course();
        if ($course && $this->permissions->canInCourse($user, 'project.view', $course)) {
            return;
        }

        abort(403);
    }

    private function assertCanJoin(ProjectAssessment $assessment): void
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return;
        }

        $this->assertSameCourse($assessment);
        $course = $assessment->course;
        if ($course && $this->permissions->canInCourse($user, 'project.join', $course)) {
            return;
        }

        abort(403);
    }

    private function assertSameCourse(ProjectAssessment $assessment): void
    {
        $course = current_course();
        if ($course && (int) $course->course_id !== (int) $assessment->course_id) {
            abort(404);
        }
    }

    private function userCanManage(): bool
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return true;
        }

        $course = current_course();

        return $course && $this->permissions->canInCourse($user, 'project.manage', $course);
    }

    private function userCanManageCourse(int $courseId): bool
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return true;
        }

        $course = \App\Models\Course::find($courseId);

        return $course && $this->permissions->canInCourse($user, 'project.manage', $course);
    }

    /**
     * @return array{can_view:bool, reason:string, grade:?ProjectMemberGrade, passed:?bool}
     */
    private function gradeVisibilityFor($user, ProjectAssessment $assessment): array
    {
        $grade = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $user->user_id)
            ->first();

        $reason = $this->visibility->hideReason($user, $assessment);
        $canView = $this->visibility->canStudentViewScore($user, $assessment);

        return [
            'can_view' => $canView,
            'reason' => $reason,
            'grade' => $grade,
            'passed' => $grade ? $grade->passed((int) $assessment->passing_percent) : null,
        ];
    }
}
