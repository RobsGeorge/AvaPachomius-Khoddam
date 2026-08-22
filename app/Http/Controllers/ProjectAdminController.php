<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Services\CoursePermissionResolver;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAdminController extends Controller
{
    public function __construct(
        private CoursePermissionResolver $permissions,
        private ProjectAdminService $admin,
        private ProjectAssignmentService $assignments,
    ) {}

    public function manage()
    {
        $this->assertCanManage();
        $course = current_course();

        $query = ProjectAssessment::with([
            'module',
            'course',
            'projects.activeMemberships.user',
            'changeRequests' => fn ($q) => $q->where('status', ProjectChangeRequest::STATUS_PENDING),
        ])->orderByDesc('created_at');

        if ($course) {
            $query->where('course_id', $course->course_id);
        }

        $assessments = $query->get();
        $modules = $this->modulesForCourse($course);

        return view('projects.manage', compact('assessments', 'modules', 'course'));
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
        ]);

        $this->admin->updateAssessment($projectAssessment, $validated);

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
            'count' => 'nullable|integer|min:1|max:30',
            'phases' => 'nullable|array',
            'phases.*.title' => 'nullable|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.deadline' => 'nullable|date',
            'deliverables' => 'nullable|array',
            'deliverables.*.title' => 'nullable|string|max:255',
            'deliverables.*.description' => 'nullable|string',
            'deliverables.*.due_at' => 'nullable|date',
        ]);

        $count = max(1, (int) ($validated['count'] ?? 1));
        for ($i = 0; $i < $count; $i++) {
            $title = $count === 1
                ? $validated['title']
                : $validated['title'].' '.($projectAssessment->projects()->count() + $i + 1);
            $this->admin->createProject($projectAssessment, [
                'title' => $title,
                'requirements' => $validated['requirements'] ?? null,
                'phases' => $validated['phases'] ?? [],
                'deliverables' => $validated['deliverables'] ?? [],
            ]);
        }

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
            'deliverables.*.due_at' => 'nullable|date',
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
            'project_count' => 'required|integer|min:1|max:30',
            'requirements' => 'nullable|string',
            'project_titles' => 'nullable|string',
            'phases' => 'nullable|array',
            'phases.*.title' => 'nullable|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.deadline' => 'nullable|date',
            'deliverables' => 'nullable|array',
            'deliverables.*.title' => 'nullable|string|max:255',
            'deliverables.*.description' => 'nullable|string',
            'deliverables.*.due_at' => 'nullable|date',
        ]);
    }

    private function assessmentPayload(array $validated): array
    {
        $course = current_course();
        $titles = [];
        if (! empty($validated['project_titles'])) {
            $titles = preg_split('/\r\n|\r|\n/', (string) $validated['project_titles']) ?: [];
        }

        return [
            'course_id' => (int) ($validated['course_id'] ?? $course?->course_id),
            'module_id' => (int) $validated['module_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'min_team_size' => (int) $validated['min_team_size'],
            'max_team_size' => (int) $validated['max_team_size'],
            'project_count' => (int) $validated['project_count'],
            'project_titles' => $titles,
            'requirements' => $validated['requirements'] ?? null,
            'phases' => $validated['phases'] ?? [],
            'deliverables' => $validated['deliverables'] ?? [],
        ];
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
}
