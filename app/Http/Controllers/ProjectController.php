<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Services\CoursePermissionResolver;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function __construct(
        private CoursePermissionResolver $permissions,
        private ProjectAssignmentService $assignments,
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
        foreach ($assessments as $assessment) {
            $memberships[$assessment->project_assessment_id] = $assessment->activeMembershipFor((int) $user->user_id);
        }

        return view('projects.index', compact('assessments', 'memberships', 'canManage'));
    }

    public function show(Project $project)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $project->load(['assessment.module', 'assessment.course', 'phases', 'deliverables', 'activeMemberships.user']);
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

        return view('projects.show', compact(
            'project',
            'assessment',
            'membership',
            'canManage',
            'pendingChange',
            'changeUsed'
        ));
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

    public function storeChangeRequest(Request $request, ProjectAssessment $projectAssessment)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->assertCanJoin($projectAssessment);

        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        $this->assignments->requestChange($projectAssessment, $user, $validated['reason']);

        return back()->with('success', __('projects.change_requested'));
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
}
