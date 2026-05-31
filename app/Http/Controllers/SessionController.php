<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Course;
use Carbon\Carbon;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with('course')
            ->orderBy('session_date', 'desc')
            ->paginate(20);

        return view('sessions.index', compact('sessions'));
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get();
        return view('sessions.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id'      => 'required|exists:course,course_id',
            'session_title'  => 'required|string|max:27',
            'creation_mode'  => 'required|in:single,multi,weekly',
            'single_date'    => 'required_if:creation_mode,single|nullable|date',
            'dates'          => 'required_if:creation_mode,multi|nullable|array|min:1',
            'dates.*'        => 'date',
            'start_date'     => 'required_if:creation_mode,weekly|nullable|date',
            'weeks'          => 'required_if:creation_mode,weekly|nullable|integer|min:1|max:52',
        ]);

        $dates = [];

        if ($request->creation_mode === 'single') {
            $dates = [$request->single_date];
        } elseif ($request->creation_mode === 'multi') {
            $dates = array_values(array_unique($request->dates));
            sort($dates);
        } elseif ($request->creation_mode === 'weekly') {
            $start = Carbon::parse($request->start_date);
            for ($i = 0; $i < (int) $request->weeks; $i++) {
                $dates[] = $start->copy()->addWeeks($i)->toDateString();
            }
        }

        $isSingle = count($dates) === 1;

        foreach ($dates as $index => $date) {
            $title = $isSingle
                ? $request->session_title
                : $request->session_title . ' ' . ($index + 1);

            Session::create([
                'course_id'     => $request->course_id,
                'session_title' => substr($title, 0, 30),
                'session_date'  => $date,
            ]);
        }

        $count = count($dates);
        return redirect()->route('sessions.index')
            ->with('success', "تم إنشاء {$count} " . ($count === 1 ? 'جلسة' : 'جلسات') . ' بنجاح');
    }

    public function edit(string $id)
    {
        $session = Session::findOrFail($id);
        $courses = Course::orderBy('title')->get();
        return view('sessions.edit', compact('session', 'courses'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'course_id'     => 'required|exists:course,course_id',
            'session_title' => 'required|string|max:30',
            'session_date'  => 'required|date',
        ]);

        $session = Session::findOrFail($id);
        $session->update($request->only('course_id', 'session_title', 'session_date'));

        return redirect()->route('sessions.index')
            ->with('success', 'تم تحديث الجلسة بنجاح');
    }

    public function destroy(string $id)
    {
        Session::findOrFail($id)->delete();
        return redirect()->route('sessions.index')
            ->with('success', 'تم حذف الجلسة بنجاح');
    }
}
