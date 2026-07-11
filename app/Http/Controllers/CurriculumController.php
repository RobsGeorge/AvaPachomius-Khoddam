<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\Module;
use App\Models\Session;
use App\Models\User;
use App\Services\CourseApplicationService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CurriculumController extends Controller
{
    public function __construct(
        private CourseApplicationService $courseApplications,
        private StudentRosterService $roster,
    ) {}

    /** Course picker — entry point for the curriculum (modules → sessions → lectures). */
    public function index()
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'instructor'])) {
            $courses = Course::orderBy('title')->get();
            $applyCourses = collect();
            $applicationStatuses = [];
        } else {
            $courses = $user->courses()->distinct()->orderBy('title')->get();
            $enrolledIds = $this->roster->studentEnrolledCourses($user)->pluck('course_id');
            $applyCourses = CourseApplicationForm::query()
                ->where('is_enabled', true)
                ->with('course')
                ->get()
                ->filter(fn (CourseApplicationForm $form) => ! $enrolledIds->contains($form->course_id))
                ->values();
            $applicationStatuses = [];
            foreach ($applyCourses as $form) {
                $applicationStatuses[$form->course_id] = $this->courseApplications->courseApplicationStatus(
                    $user,
                    $form->course_id
                );
            }
        }

        if ($courses->count() === 1 && ($user->hasAnyRole(['admin', 'instructor']) || $applyCourses->isEmpty())) {
            return redirect()->route('curriculum.show', $courses->first()->course_id);
        }

        return view('curriculum.index', compact('courses', 'applyCourses', 'applicationStatuses'));
    }

    /** Student view: curriculum organised by module, week, and lecture. */
    public function show(string $courseId)
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->roster->authorizeCourse($user, $courseId);
        }

        $course = Course::with([
            'modules.courseSessions.lectures.materials',
            'modules.lectures.materials',
            'modules.exams.schedules',
        ])->findOrFail($courseId);

        $moduleIds = $course->modules->pluck('module_id');

        $openSurveys = FeedbackSurvey::query()
            ->where('course_id', $courseId)
            ->whereIn('module_id', $moduleIds)
            ->where('status', FeedbackSurvey::STATUS_OPEN)
            ->get()
            ->groupBy('module_id');

        $submittedSurveyIds = FeedbackSubmission::query()
            ->where('user_id', Auth::user()->user_id)
            ->whereIn('survey_id', $openSurveys->flatten()->pluck('survey_id'))
            ->pluck('survey_id')
            ->flip();

        return view('course-content.show', compact('course', 'openSurveys', 'submittedSurveyIds'));
    }

    /** Admin/instructor panel: manage modules, sessions, and lectures. */
    public function admin(string $courseId)
    {
        $course = Course::with([
            'modules.courseSessions.lectures.materials',
            'modules.lectures.materials',
            'modules.exams',
            'sessions',
        ])->findOrFail($courseId);

        $linkedModuleIds  = $course->modules->pluck('module_id');
        $availableModules = Module::whereNotIn('module_id', $linkedModuleIds)
            ->orderBy('title')
            ->get();

        return view('course-content.admin', compact('course', 'availableModules'));
    }

    public function attachModule(Request $request, string $courseId)
    {
        $request->validate([
            'module_id' => 'required|exists:modules,module_id',
        ]);

        $course = Course::findOrFail($courseId);

        if (! $course->modules()->where('modules.module_id', $request->module_id)->exists()) {
            $course->modules()->attach($request->module_id, [
                'order_index' => $course->modules()->count(),
                'status' => 'draft',
            ]);
        }

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.module_linked'));
    }

    public function createAndAttachModule(Request $request, string $courseId)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
        ]);

        $course = Course::findOrFail($courseId);
        $module = Module::create($request->only('title', 'description'));
        $course->modules()->attach($module->module_id, [
            'order_index' => $course->modules()->count(),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.module_created_linked'));
    }

    public function detachModule(string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $course->modules()->detach($moduleId);

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.module_unlinked'));
    }

    public function updateModuleSettings(Request $request, string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        $data = $request->validate([
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'order_index' => 'nullable|integer|min:0|max:999',
            'status'      => 'required|in:draft,active,ended',
            'session_ids' => 'nullable|array',
            'session_ids.*' => 'integer|exists:session,session_id',
        ]);

        $course->modules()->updateExistingPivot($moduleId, [
            'start_date'  => $data['start_date'] ?? null,
            'end_date'    => $data['end_date'] ?? null,
            'order_index' => $data['order_index'] ?? ($module->pivot->order_index ?? 0),
            'status'      => $data['status'],
        ]);

        $sessionIds = collect($data['session_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validSessionIds = Session::where('course_id', $courseId)
            ->whereIn('session_id', $sessionIds)
            ->pluck('session_id');

        Session::where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->whereNotIn('session_id', $validSessionIds)
            ->update(['module_id' => null, 'week_number' => null]);

        $sync = [];
        foreach ($validSessionIds as $index => $sessionId) {
            $weekNumber = $index + 1;
            $sync[$sessionId] = ['week_number' => $weekNumber];
            Session::where('session_id', $sessionId)->update([
                'module_id'   => $moduleId,
                'week_number' => $weekNumber,
            ]);
        }
        $module->sessions()->sync($sync);

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.module_settings_saved'));
    }

    public function endModule(Request $request, string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        $course->modules()->updateExistingPivot($moduleId, [
            'status' => 'ended',
            'feedback_open' => true,
            'ended_at' => now(),
            'ended_by_user_id' => Auth::user()->user_id,
            'end_date' => $module->pivot->end_date ?? now()->toDateString(),
        ]);

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.module_ended_feedback_open'));
    }

    public function updateCourse(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);

        $validated = $request->validate([
            'title' => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'year' => 'required|integer|min:2000|max:2100',
            'default_session_start_time' => 'required|date_format:H:i',
        ]);

        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'year' => $validated['year'],
            'default_session_start_time' => $validated['default_session_start_time'].':00',
        ]);

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.course_details_saved'));
    }
}
