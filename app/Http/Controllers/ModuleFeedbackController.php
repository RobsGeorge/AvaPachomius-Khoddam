<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleFeedbackController extends Controller
{
    public function show(string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        if (! $this->feedbackIsOpen($module)) {
            return redirect()
                ->route('course-content.show', $courseId)
                ->with('warning', __('pages.module_feedback_not_open'));
        }

        $userFeedback = ModuleFeedback::where('user_id', Auth::user()->user_id)
            ->where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->first();

        return view('module-feedback.form', compact('course', 'module', 'userFeedback'));
    }

    public function store(Request $request, string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        if (! $this->feedbackIsOpen($module)) {
            return redirect()
                ->route('course-content.show', $courseId)
                ->with('error', __('pages.module_feedback_not_open'));
        }

        $data = $request->validate([
            'lecture_rating' => 'nullable|integer|min:1|max:5',
            'lecture_comments' => 'nullable|string|max:2000',
            'speaker_rating' => 'nullable|integer|min:1|max:5',
            'speaker_comments' => 'nullable|string|max:2000',
            'workshop_rating' => 'nullable|integer|min:1|max:5',
            'workshop_comments' => 'nullable|string|max:2000',
            'timing_rating' => 'nullable|integer|min:1|max:5',
            'timing_comments' => 'nullable|string|max:2000',
            'content_rating' => 'nullable|integer|min:1|max:5',
            'content_comments' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);

        ModuleFeedback::updateOrCreate(
            [
                'user_id' => Auth::user()->user_id,
                'course_id' => $courseId,
                'module_id' => $moduleId,
            ],
            $data
        );

        return redirect()
            ->route('course-content.show', $courseId)
            ->with('success', __('pages.module_feedback_saved'));
    }

    private function feedbackIsOpen(Module $module): bool
    {
        return (bool) $module->pivot?->feedback_open;
    }
}
