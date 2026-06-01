<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;

class CourseContentController extends Controller
{
    /** Student view: tabular course content organised by module */
    public function show(string $courseId)
    {
        $course = Course::with([
            'modules.lectures.materials',
        ])->findOrFail($courseId);

        return view('course-content.show', compact('course'));
    }

    /** Admin/instructor panel: manage lectures per course */
    public function admin(string $courseId)
    {
        $course = Course::with([
            'modules.lectures.materials',
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

        if (!$course->modules()->where('modules.module_id', $request->module_id)->exists()) {
            $course->modules()->attach($request->module_id);
        }

        return redirect()
            ->route('course-content.admin', $courseId)
            ->with('success', 'تم ربط الوحدة بالدورة');
    }

    /** Create a brand-new module and attach it to this course in one step */
    public function createAndAttachModule(Request $request, string $courseId)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
        ]);

        $course  = Course::findOrFail($courseId);
        $module  = Module::create($request->only('title', 'description'));
        $course->modules()->attach($module->module_id);

        return redirect()
            ->route('course-content.admin', $courseId)
            ->with('success', 'تم إنشاء الوحدة وربطها بالدورة');
    }

    /** Detach a module from this course (does not delete the module) */
    public function detachModule(string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $course->modules()->detach($moduleId);

        return redirect()
            ->route('course-content.admin', $courseId)
            ->with('success', 'تم فصل الوحدة عن الدورة');
    }
}
