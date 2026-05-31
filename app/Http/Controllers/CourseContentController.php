<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;

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

        // All modules not yet linked to this course — for the "add module" dropdown
        $linkedModuleIds = $course->modules->pluck('module_id');
        $availableModules = Module::whereNotIn('module_id', $linkedModuleIds)
            ->orderBy('title')
            ->get();

        return view('course-content.admin', compact('course', 'availableModules'));
    }
}
