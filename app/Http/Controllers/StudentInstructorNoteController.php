<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\ModuleStudentAssessmentService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentInstructorNoteController extends Controller
{
    public function __construct(
        private ModuleStudentAssessmentService $assessments,
        private StudentRosterService $roster,
    ) {}

    public function store(Request $request, Course $course, User $user)
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->canInCourse('student_notes.manage', $course), 403);
        abort_if($actor->isStudent($course->course_id) && ! $actor->isInstructorOrAdmin($course->course_id), 403);

        $enrolledIds = $this->roster->enrolledStudents($course)->pluck('user_id');
        abort_unless($enrolledIds->contains($user->user_id), 404);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'module_id' => 'nullable|exists:modules,module_id',
            'redirect_module_id' => 'nullable|exists:modules,module_id',
            'notes_filter' => 'nullable|in:all,course,module',
        ]);

        $module = null;
        if (! empty($data['module_id'])) {
            $module = Module::query()->findOrFail($data['module_id']);
            abort_unless(
                $course->modules()->where('modules.module_id', $module->module_id)->exists(),
                404
            );
        }

        $this->assessments->appendNote($user, $actor, $data['body'], $course, $module);

        if (! empty($data['redirect_module_id'])) {
            return redirect()
                ->route('module-assessments.edit', [
                    $course,
                    $data['redirect_module_id'],
                    $user,
                    'notes' => $data['notes_filter'] ?? 'all',
                ])
                ->with('success', __('pages.instructor_note_saved'));
        }

        return back()->with('success', __('pages.instructor_note_saved'));
    }
}
