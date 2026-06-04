<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleFeedback;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseContentController extends Controller
{
    /** Student view: tabular course content organised by module */
    public function show(string $courseId)
    {
        $course = Course::with([
            'modules.courseSessions.lectures.materials',
            'modules.lectures.materials',
            'modules.exams.schedules',
        ])->findOrFail($courseId);

        $userFeedbackIds = ModuleFeedback::where('user_id', Auth::user()->user_id)
            ->where('course_id', $courseId)
            ->pluck('module_id')
            ->flip();

        return view('course-content.show', compact('course', 'userFeedbackIds'));
    }

    /** Admin/instructor panel: manage lectures per course */
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

    /** Attach an existing module to this course */
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
            ->route('course-content.admin', $courseId)
            ->with('success', __('pages.module_linked'));
    }

    /** Create a brand-new module and attach it to this course in one step */
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
            ->route('course-content.admin', $courseId)
            ->with('success', __('pages.module_created_linked'));
    }

    /** Detach a module from this course (does not delete the module) */
    public function detachModule(string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $course->modules()->detach($moduleId);

        return redirect()
            ->route('course-content.admin', $courseId)
            ->with('success', __('pages.module_unlinked'));
    }

    /** Update module schedule, status, and linked sessions */
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
            ->route('course-content.admin', $courseId)
            ->with('success', __('pages.module_settings_saved'));
    }

    /** Announce module end and open student feedback */
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
            ->route('course-content.admin', $courseId)
            ->with('success', __('pages.module_ended_feedback_open'));
    }
}
