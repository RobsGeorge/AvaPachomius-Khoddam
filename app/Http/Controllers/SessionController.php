<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $mode = $request->input('creation_mode', 'single');

        if (! in_array($mode, ['single', 'multi', 'weekly'], true)) {
            $mode = 'single';
        }

        $payload = [
            'course_id'     => $request->input('course_id'),
            'session_title' => $request->input('session_title'),
            'creation_mode' => $mode,
        ];

        $datesToCreate = [];

        if ($mode === 'single') {
            $payload['single_date'] = $this->normalizeDateInput($request->input('single_date'));
        } elseif ($mode === 'multi') {
            $normalized = [];
            foreach ((array) $request->input('dates', []) as $raw) {
                $date = $this->normalizeDateInput(is_string($raw) ? $raw : null);
                if ($date !== null) {
                    $normalized[] = $date;
                }
            }
            $payload['dates'] = array_values(array_unique($normalized));
        } else {
            $payload['start_date'] = $this->normalizeDateInput($request->input('start_date'));
            $payload['weeks'] = $request->input('weeks');
        }

        $rules = [
            'course_id'     => 'required|exists:course,course_id',
            'session_title' => 'required|string|max:27',
            'creation_mode' => 'required|in:single,multi,weekly',
        ];

        if ($mode === 'single') {
            $rules['single_date'] = 'required|date_format:Y-m-d';
        } elseif ($mode === 'multi') {
            $rules['dates'] = 'required|array|min:1';
            $rules['dates.*'] = 'required|date_format:Y-m-d';
        } else {
            $rules['start_date'] = 'required|date_format:Y-m-d';
            $rules['weeks'] = 'required|integer|min:1|max:52';
        }

        $validated = validator($payload, $rules, [
            'single_date.required' => 'يرجى اختيار تاريخ الجلسة.',
            'single_date.date_format' => 'تاريخ الجلسة غير صالح. استخدم التقويم أو الصيغة يوم/شهر/سنة.',
            'dates.required' => 'يرجى إضافة تاريخ واحد على الأقل.',
            'dates.min' => 'يرجى إضافة تاريخ واحد على الأقل.',
            'dates.*.date_format' => 'أحد التواريخ غير صالح.',
            'start_date.required' => 'يرجى اختيار تاريخ أول محاضرة.',
            'start_date.date_format' => 'تاريخ البداية غير صالح.',
        ])->validate();

        if ($mode === 'single') {
            $datesToCreate = [$validated['single_date']];
        } elseif ($mode === 'multi') {
            $datesToCreate = $validated['dates'];
            sort($datesToCreate);
        } else {
            $start = Carbon::createFromFormat('Y-m-d', $validated['start_date']);
            for ($i = 0; $i < (int) $validated['weeks']; $i++) {
                $datesToCreate[] = $start->copy()->addWeeks($i)->format('Y-m-d');
            }
        }

        $isSingle = count($datesToCreate) === 1;

        try {
            foreach ($datesToCreate as $index => $date) {
                $title = $isSingle
                    ? $validated['session_title']
                    : $validated['session_title'].' '.($index + 1);

                Session::create([
                    'course_id'     => $validated['course_id'],
                    'session_title' => mb_substr($title, 0, 30),
                    'session_date'  => $date,
                ]);
            }
        } catch (QueryException $e) {
            Log::error('Session create failed', [
                'mode'    => $mode,
                'dates'   => $datesToCreate,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'تعذر حفظ الجلسة في قاعدة البيانات. تأكد من تشغيل migrations على الخادم.',
                ]);
        }

        $count = count($datesToCreate);

        return redirect()->route('sessions.index')
            ->with('success', "تم إنشاء {$count} ".($count === 1 ? 'جلسة' : 'جلسات').' بنجاح');
    }

    public function edit(string $id)
    {
        $session = Session::findOrFail($id);
        $courses = Course::orderBy('title')->get();

        return view('sessions.edit', compact('session', 'courses'));
    }

    public function update(Request $request, string $id)
    {
        $normalizedDate = $this->normalizeDateInput($request->input('session_date'));

        $validated = validator(
            [
                'course_id'     => $request->input('course_id'),
                'session_title' => $request->input('session_title'),
                'session_date'  => $normalizedDate,
            ],
            [
                'course_id'     => 'required|exists:course,course_id',
                'session_title' => 'required|string|max:30',
                'session_date'  => 'required|date_format:Y-m-d',
            ]
        )->validate();

        $session = Session::findOrFail($id);
        $session->update($validated);

        return redirect()->route('sessions.index')
            ->with('success', 'تم تحديث الجلسة بنجاح');
    }

    public function destroy(string $id)
    {
        Session::findOrFail($id)->delete();

        return redirect()->route('sessions.index')
            ->with('success', 'تم حذف الجلسة بنجاح');
    }

    /**
     * Normalize to Y-m-d for MySQL DATE / Laravel date cast.
     * Accepts native date input (Y-m-d) or displayed d/m/Y (e.g. 06/08/2026).
     */
    private function normalizeDateInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        foreach (['d/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
