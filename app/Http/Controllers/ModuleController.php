<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Module;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::withCount('lectures')->with('courses')->get();
        return view('modules.index', compact('modules'));
    }

    public function create()
    {
        return view('modules.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
        ]);

        Module::create($request->only('title', 'description'));

        return redirect()->route('modules.index')->with('success', 'تم إنشاء الوحدة بنجاح');
    }

    public function edit(string $id)
    {
        $module = Module::with('courses')->findOrFail($id);
        return view('modules.edit', compact('module'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
        ]);

        Module::findOrFail($id)->update($request->only('title', 'description'));

        return redirect()->route('modules.index')->with('success', 'تم تحديث الوحدة بنجاح');
    }

    public function destroy(string $id)
    {
        Module::findOrFail($id)->delete();
        return redirect()->route('modules.index')->with('success', 'تم حذف الوحدة');
    }
}
