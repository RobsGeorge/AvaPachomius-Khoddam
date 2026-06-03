<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::withCount(['lectures', 'exams'])
            ->with('courses')
            ->orderBy('title')
            ->get();

        return view('modules.index', compact('modules'));
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get();

        return view('modules.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'course_id'   => 'nullable|exists:course,course_id',
        ]);

        $module = Module::create($request->only('title', 'description'));

        if ($request->filled('course_id')) {
            $this->attachModuleToCourse($module, (int) $request->course_id);
        }

        return redirect()->route('modules.index')
            ->with('success', __('pages.module_created_success'));
    }

    public function edit(string $id)
    {
        $module = Module::with('courses')->findOrFail($id);
        $courses = Course::orderBy('title')->get();
        $linkedCourseIds = $module->courses->pluck('course_id')->all();

        return view('modules.edit', compact('module', 'courses', 'linkedCourseIds'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'course_id'   => 'nullable|exists:course,course_id',
        ]);

        $module = Module::findOrFail($id);
        $module->update($request->only('title', 'description'));

        if ($request->filled('course_id')
            && ! $module->courses()->where('course_id', $request->course_id)->exists()) {
            $this->attachModuleToCourse($module, (int) $request->course_id);
        }

        return redirect()->route('modules.index')
            ->with('success', __('pages.module_updated_success'));
    }

    public function destroy(string $id)
    {
        Module::findOrFail($id)->delete();

        return redirect()->route('modules.index')
            ->with('success', __('pages.module_deleted_success'));
    }

    private function attachModuleToCourse(Module $module, int $courseId): void
    {
        $nextOrder = (int) DB::table('course_module')
            ->where('course_id', $courseId)
            ->max('order_index') + 1;

        $module->courses()->attach($courseId, [
            'order_index' => $nextOrder,
            'status'      => 'active',
        ]);
    }
}
