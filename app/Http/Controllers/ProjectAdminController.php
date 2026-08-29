<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CoursePermissionResolver;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradebookSyncService;
use App\Services\ProjectGradingService;
use App\Services\ProjectSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAdminController extends Controller
{
    public function __construct(
        private CoursePermissionResolver $permissions,
        private ProjectAdminService $admin,
        private ProjectAssignmentService $assignments,
        private ProjectGradingService $grading,
        private ProjectSubmissionService $submissions,
        private ProjectGradebookSyncService $gradebook,
    ) {}

    public function manage()
    {
        $this->assertCanManage();
        $course = current_course();

        $query = ProjectAssessment::with([
            'module',
            'course',
            'projects.activeMemberships.user',
            'projects.deliverables',
            'projects.teamGrade',
            'changeRequests' => fn ($q) => $q->where('status', ProjectChangeRequest::STATUS_PENDING),
        ])->orderByDesc('created_at');

        if ($course) {
            $query->where('course_id', $course->course_id);
        }

        $assessments = $query->get();
        foreach ($assessments as $assessment) {
            $this->assignments->markBelowMinimumAfterJoinClose($assessment);
        }
        $assessments = $query->get();
        $modules = $this->modulesForCourse($course);

        $submissionProgress = [];
        $overview = [];
        foreach ($assessments as $assessment) {
            $teams = $assessment->projects;
            $seated = 0;
            $requiredDeliverables = 0;
            $missingDeliverables = 0;

            foreach ($teams as $project) {
                $progress = $this->submissions->progress($project);
                $submissionProgress[$project->project_id] = $progress;
                $seated += $project->activeMemberships->count();
                $requiredDeliverables += $progress['required'];
                $missingDeliverables += $progress['missing'];
            }

            $live = $teams->reject(fn (Project $p) => $p->isCancelled());
            $overview[$assessment->project_assessment_id] = [
                'teams' => $live->count(),
                'cancelled' => $teams->count() - $live->count(),
                'locked' => $live->filter(fn (Project $p) => $p->isLocked())->count(),
                'below_minimum' => $live->filter(fn (Project $p) => (bool) $p->below_minimum)->count(),
                'full' => $live->filter(fn (Project $p) => $p->isClosed())->count(),
                'seated' => $seated,
                'capacity' => $live->count() * (int) $assessment->max_team_size,
                'required_deliverables' => $requiredDeliverables,
                'missing_deliverables' => $missingDeliverables,
                'graded_teams' => $live->filter(fn (Project $p) => $p->teamGrade !== null)->count(),
            ];
        }

        return view('projects.manage', compact(
            'assessments',
            'modules',
            'course',
            'submissionProgress',
            'overview'
        ));
    }

    public function store(Request $request)
    {
        $this->assertCanManage();
        $validated = $this->validateAssessment($request);
        $courseId = (int) ($validated['course_id'] ?? current_course()?->course_id);
        $this->assertModuleBelongsToCourse((int) $validated['module_id'], $courseId);
        $this->assertCanManageCourse($courseId);
        $validated['course_id'] = $courseId;

        $this->admin->createAssessment($this->assessmentPayload($validated), Auth::user());

        return redirect()->route('projects.manage')
            ->with('success', __('projects.assessment_created'));
    }

    public function update(Request $request, ProjectAssessment $projectAssessment)
    {
        $this->assertCanManageCourse((int) $projectAssessment->course_id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_team_size' => 'required|integer|min:1|max:50',
            'max_team_size' => 'required|integer|min:1|max:50',
            'max_points' => 'nullable|numeric|min:0.01|max:9999.99',
            'passing_percent' => 'nullable|integer|min:0|max:100',
            'join_closes_at' => 'required|date',
            'seed_pool_size' => 'nullable|integer|min:1|max:200',
            'sync_to_gradebook' => 'nullable|boolean',
        ]);

        $assessment = $this->admin->updateAssessment($projectAssessment, $validated);

        // Turning the toggle on after the announcement should still push the grades.
        if ($assessment->sync_to_gradebook && $assessment->areResultsAnnounced()) {
            $this->gradebook->sync($assessment, Auth::user());
        }

        return back()->with('success', __('projects.assessment_updated'));
    }

    public function publish(ProjectAssessment $projectAssessment)
    {
        $this->assertCanManageCourse((int) $projectAssessment->course_id);
        $this->admin->publish($projectAssessment, ! $projectAssessment->is_published);

        return back()->with('success', $projectAssessment->fresh()->is_published
            ? __('projects.published')
            : __('projects.unpublished'));
    }

    public function destroy(ProjectAssessment $projectAssessment)
    {
        $this->assertCanManageCourse((int) $projectAssessment->course_id);
        $this->admin->deleteAssessment($projectAssessment);

        return redirect()->route('projects.manage')
            ->with('success', __('projects.assessment_deleted'));
    }

    public function storeProject(Request $request, ProjectAssessment $projectAssessment)
    {
        $this->assertCanManageCourse((int) $projectAssessment->course_id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'requirements' => 'nullable|string',
            'phases' => 'nullable|array',
            'phases.*.title' => 'nullable|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.deadline' => 'nullable|date',
            'deliverables' => 'nullable|array',
            'deliverables.*.title' => 'nullable|string|max:255',
            'deliverables.*.description' => 'nullable|string',
            'deliverables.*.instructions' => 'nullable|string',
            'deliverables.*.due_at' => 'nullable|date',
            'deliverables.*.submission_type' => 'nullable|string|in:pdf,document,image,zip,link,text',
            'deliverables.*.file_mode' => 'nullable|string|in:single,multi',
            'deliverables.*.is_required' => 'nullable|boolean',
            'deliverables.*.allow_late' => 'nullable|boolean',
        ]);

        $this->admin->createProject($projectAssessment, [
            'title' => $validated['title'],
            'requirements' => $validated['requirements'] ?? null,
            'phases' => $validated['phases'] ?? [],
            'deliverables' => $validated['deliverables'] ?? [],
        ]);

        return back()->with('success', __('projects.project_created'));
    }

    public function updateProject(Request $request, Project $project)
    {
        $project->load('assessment');
        $this->assertCanManageCourse((int) $project->assessment->course_id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'requirements' => 'nullable|string',
            'phases' => 'nullable|array',
            'phases.*.title' => 'nullable|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.deadline' => 'nullable|date',
            'deliverables' => 'nullable|array',
            'deliverables.*.title' => 'nullable|string|max:255',
            'deliverables.*.description' => 'nullable|string',
            'deliverables.*.instructions' => 'nullable|string',
            'deliverables.*.due_at' => 'nullable|date',
            'deliverables.*.submission_type' => 'nullable|string|in:pdf,document,image,zip,link,text',
            'deliverables.*.file_mode' => 'nullable|string|in:single,multi',
            'deliverables.*.is_required' => 'nullable|boolean',
            'deliverables.*.allow_late' => 'nullable|boolean',
        ]);

        $this->admin->updateProject($project, $validated);

        return back()->with('success', __('projects.project_updated'));
    }

    public function destroyProject(Project $project)
    {
        $project->load('assessment');
        $this->assertCanManageCourse((int) $project->assessment->course_id);
        $this->admin->deleteProject($project);

        return back()->with('success', __('projects.project_deleted'));
    }

    public function updateWorkspace(Request $request, Project $project)
    {
        $project->load('assessment');
        $this->assertCanManageCourse((int) $project->assessment->course_id);

        $validated = $request->validate([
            'team_workspace_url' => 'nullable|string|max:2048',
            'team_announcement' => 'nullable|string|max:4000',
        ]);

        $this->admin->updateTeamWorkspace($project, $validated);

        return back()->with('success', __('projects.workspace_saved'));
    }

    public function lockProject(Request $request, Project $project)
    {
        $project->load('assessment');
        $this->assertCanManageCourse((int) $project->assessment->course_id);
        $locked = ! $project->isLocked();
        $this->assignments->lockTeam($project, Auth::user(), $locked);

        return back()->with('success', $locked
            ? __('projects.team_locked')
            : __('projects.team_unlocked'));
    }

    public function cancelProject(Project $project)
    {
        $project->load('assessment');
        $this->assertCanManageCourse((int) $project->assessment->course_id);

        if ($project->isCancelled()) {
            $this->assignments->restoreTeam($project, Auth::user());

            return back()->with('success', __('projects.team_restored'));
        }

        $this->assignments->cancelTeam($project, Auth::user());

        return back()->with('success', __('projects.team_cancelled_done'));
    }

    public function moveMember(Request $request, ProjectMembership $membership)
    {
        $membership->load('assessment');
        $assessment = $membership->assessment;
        abort_unless($assessment, 404);
        $this->assertCanManageCourse((int) $assessment->course_id);

        $validated = $request->validate([
            'to_project_id' => 'required|integer',
        ]);

        $target = Project::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->whereKey((int) $validated['to_project_id'])
            ->firstOrFail();

        $this->assignments->moveMember($membership, $target, Auth::user());

        return back()->with('success', __('projects.member_moved'));
    }

    public function mergeProjects(Request $request, Project $project)
    {
        $project->load('assessment');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        $this->assertCanManageCourse((int) $assessment->course_id);

        $validated = $request->validate([
            'into_project_id' => 'required|integer',
        ]);

        $into = Project::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->whereKey((int) $validated['into_project_id'])
            ->firstOrFail();

        $result = $this->assignments->mergeTeams($project, $into, Auth::user());

        return back()->with('success', __('projects.teams_merged', ['count' => $result['moved']]));
    }

    public function changeRequests(Request $request)
    {
        $this->assertCanManage();
        $course = current_course();

        $query = ProjectChangeRequest::with(['user', 'assessment', 'fromProject'])
            ->where('status', ProjectChangeRequest::STATUS_PENDING)
            ->orderBy('created_at');

        if ($course) {
            $query->whereHas('assessment', fn ($q) => $q->where('course_id', $course->course_id));
        }

        if ($request->filled('assessment')) {
            $query->where('project_assessment_id', (int) $request->query('assessment'));
        }

        $requests = $query->get();

        return view('projects.change-requests', compact('requests'));
    }

    public function approveChange(ProjectChangeRequest $changeRequest)
    {
        $changeRequest->load('assessment');
        $this->assertCanManageCourse((int) $changeRequest->assessment->course_id);
        $this->assignments->approveChange($changeRequest, Auth::user());

        return back()->with('success', __('projects.change_approved'));
    }

    public function rejectChange(Request $request, ProjectChangeRequest $changeRequest)
    {
        $changeRequest->load('assessment');
        $this->assertCanManageCourse((int) $changeRequest->assessment->course_id);
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);
        $this->assignments->rejectChange($changeRequest, Auth::user(), $validated['admin_notes'] ?? null);

        return back()->with('success', __('projects.change_rejected'));
    }

    public function grades(ProjectAssessment $projectAssessment)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $projectAssessment->load([
            'module',
            'course',
            'criteria',
            'projects.activeMemberships.user',
            'projects.teamGrade.scores',
            'memberGrades',
        ]);

        $memberGrades = $projectAssessment->memberGrades->keyBy('user_id');

        $teamRubrics = [];
        foreach ($projectAssessment->projects as $project) {
            $teamRubrics[$project->project_id] = [
                'criteria' => $this->grading->criterionBreakdown($projectAssessment, $project),
                'custom' => $this->grading->teamHasCustomRubric($project),
            ];
        }

        return view('projects.grades', [
            'assessment' => $projectAssessment,
            'memberGrades' => $memberGrades,
            'maxPoints' => $this->grading->maxPoints($projectAssessment),
            'teamRubrics' => $teamRubrics,
        ]);
    }

    /** CSV of every team, member, submission progress and grade for one assessment. */
    public function exportCsv(ProjectAssessment $projectAssessment): StreamedResponse
    {
        $this->assertCanManageCourse((int) $projectAssessment->course_id);
        $projectAssessment->load([
            'course',
            'module',
            'projects.activeMemberships.user',
            'projects.deliverables',
            'projects.teamGrade',
            'memberGrades',
        ]);

        $memberGrades = $projectAssessment->memberGrades->keyBy('user_id');
        $maxPoints = $this->grading->maxPoints($projectAssessment);
        $filename = 'project-roster-'.$projectAssessment->project_assessment_id.'-'.now()->format('Y-m-d').'.csv';

        $rows = [];
        foreach ($projectAssessment->projects as $project) {
            $progress = $this->submissions->progress($project);
            $teamGrade = $project->teamGrade;

            $memberships = $project->activeMemberships;
            if ($memberships->isEmpty()) {
                $rows[] = [$project, null, $progress, $teamGrade];

                continue;
            }

            foreach ($memberships as $membership) {
                $rows[] = [$project, $membership, $progress, $teamGrade];
            }
        }

        AuditLogService::recordEvent('project.roster_exported', [
            'project_assessment_id' => $projectAssessment->project_assessment_id,
            'course_id' => $projectAssessment->course_id,
            'row_count' => count($rows),
            'actor_user_id' => Auth::id(),
        ]);

        return response()->streamDownload(function () use ($projectAssessment, $rows, $memberGrades, $maxPoints) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'assessment_id',
                'assessment_title',
                'course_title',
                'module_title',
                'team_title',
                'team_status',
                'is_locked',
                'below_minimum',
                'is_cancelled',
                'seats_filled',
                'max_team_size',
                'deliverables_required',
                'deliverables_submitted',
                'deliverables_missing',
                'deliverables_late',
                'team_points',
                'team_percent',
                'student_name',
                'student_email',
                'student_mobile',
                'student_points',
                'student_percent',
                'grade_source',
                'max_points',
            ]);

            foreach ($rows as [$project, $membership, $progress, $teamGrade]) {
                $student = $membership?->user;
                $memberGrade = $student ? $memberGrades->get($student->user_id) : null;

                fputcsv($out, [
                    $projectAssessment->project_assessment_id,
                    $projectAssessment->title,
                    $projectAssessment->course?->title,
                    $projectAssessment->module?->title,
                    $project->title,
                    $project->isClosed() ? 'closed' : 'open',
                    $project->is_locked ? '1' : '0',
                    $project->below_minimum ? '1' : '0',
                    $project->isCancelled() ? '1' : '0',
                    $project->activeMemberships->count(),
                    $projectAssessment->max_team_size,
                    $progress['required'],
                    $progress['submitted'],
                    $progress['missing'],
                    $progress['late'],
                    $teamGrade ? number_format((float) $teamGrade->points, 2, '.', '') : '',
                    $teamGrade ? number_format((float) $teamGrade->percent, 2, '.', '') : '',
                    $student?->displayName(),
                    $student?->email,
                    $student?->mobile_number,
                    $memberGrade ? number_format((float) $memberGrade->points, 2, '.', '') : '',
                    $memberGrade ? number_format((float) $memberGrade->percent, 2, '.', '') : '',
                    $memberGrade?->source,
                    $maxPoints,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function syncTeamCriteria(Request $request, Project $project)
    {
        $project->load('assessment.criteria');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        $this->assertCanGradeCourse((int) $assessment->course_id);

        $validated = $request->validate([
            'team_criteria' => 'nullable|array',
            'team_criteria.*.project_grade_criterion_id' => 'nullable|integer',
            'team_criteria.*.title' => 'nullable|string|max:255',
            'team_criteria.*.max_points' => 'nullable|numeric|min:0|max:9999.99',
            'team_criteria.*.is_excluded' => 'nullable|boolean',
        ]);

        $this->grading->syncTeamCriteria(
            $assessment,
            $project,
            $validated['team_criteria'] ?? [],
            Auth::user()
        );

        return back()->with('success', __('projects.team_rubric_saved'));
    }

    public function resetTeamCriteria(Project $project)
    {
        $project->load('assessment.criteria');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        $this->assertCanGradeCourse((int) $assessment->course_id);

        $this->grading->resetTeamCriteria($assessment, $project, Auth::user());

        return back()->with('success', __('projects.team_rubric_reset_done'));
    }

    public function syncCriteria(Request $request, ProjectAssessment $projectAssessment)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $validated = $request->validate([
            'criteria' => 'nullable|array',
            'criteria.*.project_grade_criterion_id' => 'nullable|integer',
            'criteria.*.title' => 'nullable|string|max:255',
            'criteria.*.max_points' => 'nullable|numeric|min:0.01|max:9999.99',
        ]);

        $this->grading->syncCriteria($projectAssessment, $validated['criteria'] ?? []);

        return back()->with('success', __('projects.criteria_saved'));
    }

    public function updateScale(Request $request, ProjectAssessment $projectAssessment)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $validated = $request->validate([
            'max_points' => 'nullable|numeric|min:0.01|max:9999.99',
            'passing_percent' => 'required|integer|min:0|max:100',
        ]);

        $this->grading->updateScale($projectAssessment, $validated);

        return back()->with('success', __('projects.scale_saved'));
    }

    public function announce(ProjectAssessment $projectAssessment)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $this->grading->announce($projectAssessment, Auth::user());

        return back()->with('success', __('projects.results_announced'));
    }

    public function gradeTeam(Request $request, Project $project)
    {
        $project->load('assessment.criteria');
        $assessment = $project->assessment;
        abort_unless($assessment, 404);
        $this->assertCanGradeCourse((int) $assessment->course_id);

        $rules = [
            'notes' => 'nullable|string|max:4000',
            'points' => 'nullable|numeric|min:0|max:9999.99',
            'scores' => 'nullable|array',
            'scores.*' => 'nullable|numeric|min:0|max:9999.99',
        ];
        $validated = $request->validate($rules);

        $this->grading->gradeTeam(
            $assessment,
            $project,
            $validated['scores'] ?? [],
            Auth::user(),
            $validated['notes'] ?? null,
            array_key_exists('points', $validated) && $validated['points'] !== null
                ? (float) $validated['points']
                : null,
        );

        return back()->with('success', __('projects.team_grade_saved'));
    }

    public function gradeStudent(Request $request, ProjectAssessment $projectAssessment, User $user)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $validated = $request->validate([
            'points' => 'required|numeric|min:0|max:9999.99',
        ]);

        $this->grading->gradeStudent(
            $projectAssessment,
            $user,
            (float) $validated['points'],
            Auth::user()
        );

        return back()->with('success', __('projects.student_grade_saved'));
    }

    public function clearStudentGrade(ProjectAssessment $projectAssessment, User $user)
    {
        $this->assertCanGradeCourse((int) $projectAssessment->course_id);
        $this->grading->clearStudentOverride($projectAssessment, $user, Auth::user());

        return back()->with('success', __('projects.student_override_cleared'));
    }

    private function validateAssessment(Request $request): array
    {
        $course = current_course();

        return $request->validate([
            'course_id' => ($course ? 'nullable' : 'required').'|exists:course,course_id',
            'module_id' => 'required|exists:modules,module_id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_team_size' => 'required|integer|min:1|max:50',
            'max_team_size' => 'required|integer|min:1|max:50',
            'max_points' => 'nullable|numeric|min:0.01|max:9999.99',
            'passing_percent' => 'nullable|integer|min:0|max:100',
            'join_closes_at' => 'required|date',
            'seed_pool_size' => 'nullable|integer|min:1|max:200',
            'sync_to_gradebook' => 'nullable|boolean',
            'criteria' => 'nullable|array',
            'criteria.*.title' => 'nullable|string|max:255',
            'criteria.*.max_points' => 'nullable|numeric|min:0.01|max:9999.99',
            'project_count' => 'nullable|integer|min:1|max:30',
            'subprojects' => 'nullable|array',
            'subprojects.*.title' => 'nullable|string|max:255',
            'subprojects.*.requirements' => 'nullable|string',
            'requirements' => 'nullable|string',
            'project_titles' => 'nullable|string',
            'phases' => 'nullable|array',
            'phases.*.title' => 'nullable|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.deadline' => 'nullable|date',
            'deliverables' => 'nullable|array',
            'deliverables.*.title' => 'nullable|string|max:255',
            'deliverables.*.description' => 'nullable|string',
            'deliverables.*.instructions' => 'nullable|string',
            'deliverables.*.due_at' => 'nullable|date',
            'deliverables.*.submission_type' => 'nullable|string|in:pdf,document,image,zip,link,text',
            'deliverables.*.file_mode' => 'nullable|string|in:single,multi',
            'deliverables.*.is_required' => 'nullable|boolean',
            'deliverables.*.allow_late' => 'nullable|boolean',
        ]);
    }

    private function assessmentPayload(array $validated): array
    {
        $course = current_course();
        $titles = [];
        if (! empty($validated['project_titles'])) {
            $titles = preg_split('/\r\n|\r|\n/', (string) $validated['project_titles']) ?: [];
        }

        $payload = [
            'course_id' => (int) ($validated['course_id'] ?? $course?->course_id),
            'module_id' => (int) $validated['module_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'min_team_size' => (int) $validated['min_team_size'],
            'max_team_size' => (int) $validated['max_team_size'],
            'max_points' => $validated['max_points'] ?? 100,
            'passing_percent' => (int) ($validated['passing_percent'] ?? 50),
            'join_closes_at' => $validated['join_closes_at'] ?? null,
            'seed_pool_size' => isset($validated['seed_pool_size'])
                ? (int) $validated['seed_pool_size']
                : null,
            'sync_to_gradebook' => (bool) ($validated['sync_to_gradebook'] ?? false),
            'criteria' => $validated['criteria'] ?? [],
            'project_count' => (int) ($validated['project_count'] ?? 1),
            'project_titles' => $titles,
            'requirements' => $validated['requirements'] ?? null,
            'phases' => $validated['phases'] ?? [],
            'deliverables' => $validated['deliverables'] ?? [],
        ];

        if (array_key_exists('subprojects', $validated)) {
            $payload['subprojects'] = $validated['subprojects'] ?? [];
        }

        return $payload;
    }

    private function modulesForCourse(?Course $course)
    {
        $query = Module::query()->orderBy('title');
        if ($course) {
            $query->whereHas('courses', fn ($q) => $q->where('course.course_id', $course->course_id));
        }

        return $query->get();
    }

    private function assertModuleBelongsToCourse(int $moduleId, int $courseId): void
    {
        $linked = DB::table('course_module')
            ->where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'module_id' => __('pages.module_not_in_course'),
            ]);
        }
    }

    private function assertCanManage(): void
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return;
        }

        $course = current_course();
        if ($course && $this->permissions->canInCourse($user, 'project.manage', $course)) {
            return;
        }

        abort(403);
    }

    private function assertCanManageCourse(int $courseId): void
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return;
        }

        $course = Course::findOrFail($courseId);
        if ($this->permissions->canInCourse($user, 'project.manage', $course)) {
            return;
        }

        abort(403);
    }

    private function assertCanGradeCourse(int $courseId): void
    {
        $user = Auth::user();
        if ($user?->is_superadmin) {
            return;
        }

        $course = Course::findOrFail($courseId);
        if ($this->permissions->canInCourse($user, 'project.grade', $course)
            || $this->permissions->canInCourse($user, 'project.manage', $course)) {
            return;
        }

        abort(403);
    }
}
