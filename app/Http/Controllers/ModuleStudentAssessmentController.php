<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleStudentAssessment;
use App\Models\User;
use App\Services\ModuleStudentAssessmentService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleStudentAssessmentController extends Controller
{
    public function __construct(
        private ModuleStudentAssessmentService $assessments,
        private StudentRosterService $roster,
    ) {}

    public function index(Course $course, Module $module)
    {
        $this->authorizeView($course);
        $this->assertModuleAttachedAndEnded($course, $module);

        $students = $this->roster->enrolledStudents($course);
        $existing = ModuleStudentAssessment::query()
            ->where('course_id', $course->course_id)
            ->where('module_id', $module->module_id)
            ->get()
            ->keyBy('user_id');

        return view('assessments.module.index', compact('course', 'module', 'students', 'existing'));
    }

    public function edit(Course $course, Module $module, User $user)
    {
        $this->authorizeManage($course);
        $this->assertModuleAttachedAndEnded($course, $module);
        $this->assertStudentEnrolled($course, $user);

        $churchId = $this->assessments->churchIdForCourse($course);
        $criteria = $this->assessments->ensureCriteriaForChurch($churchId);

        $assessment = ModuleStudentAssessment::query()
            ->with('scores')
            ->where('course_id', $course->course_id)
            ->where('module_id', $module->module_id)
            ->where('user_id', $user->user_id)
            ->first();

        $scoresByCriterion = $assessment
            ? $assessment->scores->pluck('score', 'criterion_id')
            : collect();

        $noteFilter = request('notes', 'all');
        $notes = Auth::user()->canInCourse('student_notes.view', $course)
            ? $this->assessments->notesForSubject($churchId, $user, $noteFilter, $course, $module)
            : collect();

        $canManageNotes = Auth::user()->canInCourse('student_notes.manage', $course);

        return view('assessments.module.edit', compact(
            'course',
            'module',
            'user',
            'criteria',
            'assessment',
            'scoresByCriterion',
            'notes',
            'noteFilter',
            'canManageNotes',
        ));
    }

    public function update(Request $request, Course $course, Module $module, User $user)
    {
        $this->authorizeManage($course);
        $this->assertModuleAttachedAndEnded($course, $module);
        $this->assertStudentEnrolled($course, $user);

        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'nullable|integer|min:0|max:10',
            'status' => 'required|in:draft,final',
        ]);

        $scores = collect($data['scores'] ?? [])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->assessments->saveAssessment(
            $course,
            $module,
            $user,
            Auth::user(),
            $scores,
            $data['status'],
        );

        return redirect()
            ->route('module-assessments.edit', [$course, $module, $user])
            ->with('success', __('pages.assessment_saved'));
    }

    private function authorizeView(Course $course): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->canInCourse('student_assessment.view', $course), 403);
        abort_if($actor->isStudent($course->course_id) && ! $actor->isInstructorOrAdmin($course->course_id), 403);
    }

    private function authorizeManage(Course $course): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->canInCourse('student_assessment.manage', $course), 403);
        abort_if($actor->isStudent($course->course_id) && ! $actor->isInstructorOrAdmin($course->course_id), 403);
    }

    private function assertModuleAttachedAndEnded(Course $course, Module $module): void
    {
        abort_unless(
            $course->modules()->where('modules.module_id', $module->module_id)->exists(),
            404
        );
        abort_unless($this->assessments->moduleIsEnded($course, $module), 403, __('pages.assessment_module_not_ended'));
    }

    private function assertStudentEnrolled(Course $course, User $user): void
    {
        $enrolledIds = $this->roster->enrolledStudents($course)->pluck('user_id');
        abort_unless($enrolledIds->contains($user->user_id), 404);
    }
}
