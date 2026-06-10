<?php

namespace App\Http\Controllers;

use App\Models\Lecture;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LectureController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'session_id'   => 'required|exists:session,session_id',
            'course_id'    => 'required|exists:course,course_id',
            'module_id'    => 'required|exists:modules,module_id',
            'title'        => 'required|string|max:150',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_link'  => 'nullable|url|max:500',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        $session = $this->resolveSessionForModule(
            (int) $request->session_id,
            (int) $request->module_id,
            (int) $request->course_id
        );

        Lecture::create([
            'module_id'    => $request->module_id,
            'session_id'   => $session->session_id,
            'title'        => $request->title,
            'week_number'  => $session->week_number ?? 1,
            'lecture_date' => $request->lecture_date ?? $session->session_date,
            'video_link'   => $request->video_link,
            'slides_link'  => $request->slides_link,
            'notes'        => $request->notes,
            'order_index'  => $request->order_index ?? 0,
        ]);

        return redirect()
            ->route('curriculum.admin', $request->course_id)
            ->with('success', __('pages.lecture_created_success'));
    }

    public function edit(string $id)
    {
        $lecture = Lecture::with('materials', 'module.courses', 'module.courseSessions', 'session')
            ->findOrFail($id);

        return view('lectures.edit', compact('lecture'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'session_id'   => 'required|exists:session,session_id',
            'title'        => 'required|string|max:150',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_link'  => 'nullable|url|max:500',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        $lecture = Lecture::with('module.courses')->findOrFail($id);
        $course = $lecture->module->courses->first();

        $session = $this->resolveSessionForModule(
            (int) $request->session_id,
            (int) $lecture->module_id,
            $course ? (int) $course->course_id : null
        );

        $lecture->update([
            'session_id'   => $session->session_id,
            'title'        => $request->title,
            'week_number'  => $session->week_number ?? $lecture->week_number,
            'lecture_date' => $request->lecture_date,
            'video_link'   => $request->video_link,
            'slides_link'  => $request->slides_link,
            'notes'        => $request->notes,
            'order_index'  => $request->order_index ?? 0,
        ]);

        return redirect()
            ->route('lectures.edit', $id)
            ->with('success', __('pages.lecture_updated_success'));
    }

    public function destroy(Request $request, string $id)
    {
        $lecture = Lecture::findOrFail($id);
        $courseId = $request->input('course_id');
        $lecture->delete();

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.lecture_deleted_success'));
    }

    private function resolveSessionForModule(int $sessionId, int $moduleId, ?int $courseId): Session
    {
        $session = Session::where('session_id', $sessionId)
            ->where('module_id', $moduleId)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_id' => [__('pages.session_not_in_module')],
            ]);
        }

        if ($courseId !== null && (int) $session->course_id !== $courseId) {
            throw ValidationException::withMessages([
                'session_id' => [__('pages.session_not_in_course')],
            ]);
        }

        return $session;
    }
}
