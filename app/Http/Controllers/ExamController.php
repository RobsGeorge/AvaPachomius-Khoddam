<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::with(['schedules', 'results' => function($query) {
            $query->where('user_id', Auth::id());
        }])->get();

        return view('exams.index', compact('exams'));
    }

    public function dashboard()
    {
        $exams = Exam::with(['schedules', 'results'])->get();
        return view('exams.dashboard', compact('exams'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources' => 'nullable|string',
        ]);

        Exam::create($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', 'تم إضافة الامتحان بنجاح');
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'exam_name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources' => 'nullable|string',
        ]);

        $exam->update($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', 'تم تحديث الامتحان بنجاح');
    }

    public function scheduleExam(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date|after:now',
        ]);

        $exam->schedules()->create($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', 'تم جدولة الامتحان بنجاح');
    }

    public function updateResult(Request $request, ExamResult $result)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
        ]);

        $result->update($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', 'تم تحديث الدرجة بنجاح');
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();
        return redirect()->route('exams.dashboard')
            ->with('success', 'تم حذف الامتحان بنجاح');
    }

    public function adminDashboard()
    {
        // Get statistics
        $totalExams = Exam::count();
        $upcomingExams = ExamSchedule::where('scheduled_date', '>', now())->count();
        $completedExams = ExamSchedule::where('is_completed', true)->count();

        // Get upcoming exam schedules with related data
        $upcomingExamSchedules = ExamSchedule::with(['exam', 'results'])
            ->where('scheduled_date', '>', now())
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        // Get recent results
        $recentResults = ExamResult::with(['user', 'exam', 'schedule'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('exams.admin-dashboard', compact(
            'totalExams',
            'upcomingExams',
            'completedExams',
            'upcomingExamSchedules',
            'recentResults'
        ));
    }
} 