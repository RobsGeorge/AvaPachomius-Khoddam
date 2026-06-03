<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleController extends Controller
{
    public function index()
    {
        $query = Module::with('courses')->orderBy('title');
        $counts = ['lectures'];

        $showExamCount = Schema::hasTable('exams') && Schema::hasColumn('exams', 'module_id');

        if ($showExamCount) {
            $counts[] = 'exams';
        }

        $modules = $query->withCount($counts)->get();

        return view('modules.index', compact('modules', 'showExamCount'));
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
        $pivot = [];

        if (Schema::hasColumn('course_module', 'order_index')) {
            $nextOrder = (int) DB::table('course_module')
                ->where('course_id', $courseId)
                ->max('order_index');

            $pivot['order_index'] = $nextOrder + 1;
        }

        if (Schema::hasColumn('course_module', 'status')) {
            $pivot['status'] = 'draft';
        }

        $module->courses()->attach($courseId, $pivot);
    }
}
