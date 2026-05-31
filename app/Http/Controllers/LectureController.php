<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lecture;
use App\Models\Module;

class LectureController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'module_id'    => 'required|exists:modules,module_id',
            'course_id'    => 'required|exists:course,course_id',
            'title'        => 'required|string|max:150',
            'week_number'  => 'required|integer|min:1|max:99',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_link'  => 'nullable|url|max:500',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        Lecture::create([
            'module_id'    => $request->module_id,
            'title'        => $request->title,
            'week_number'  => $request->week_number,
            'lecture_date' => $request->lecture_date,
            'video_link'   => $request->video_link,
            'slides_link'  => $request->slides_link,
            'notes'        => $request->notes,
            'order_index'  => $request->order_index ?? 0,
        ]);

        return redirect()
            ->route('course-content.admin', $request->course_id)
            ->with('success', 'تمت إضافة المحاضرة بنجاح');
    }

    public function edit(string $id)
    {
        $lecture = Lecture::with('materials', 'module.courses')->findOrFail($id);
        return view('lectures.edit', compact('lecture'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'        => 'required|string|max:150',
            'week_number'  => 'required|integer|min:1|max:99',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_link'  => 'nullable|url|max:500',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        $lecture = Lecture::findOrFail($id);
        $lecture->update($request->only(
            'title', 'week_number', 'lecture_date',
            'video_link', 'slides_link', 'notes', 'order_index'
        ));

        return redirect()
            ->route('lectures.edit', $id)
            ->with('success', 'تم تحديث المحاضرة بنجاح');
    }

    public function destroy(Request $request, string $id)
    {
        $lecture = Lecture::findOrFail($id);
        $courseId = $request->input('course_id');
        $lecture->delete();

        return redirect()
            ->route('course-content.admin', $courseId)
            ->with('success', 'تم حذف المحاضرة');
    }
}
