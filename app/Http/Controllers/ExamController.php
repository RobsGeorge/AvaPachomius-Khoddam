<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        // Log the incoming request data
        Log::info('Exam creation request received', [
            'all_data' => $request->all(),
            'validated_data' => $request->validated() ?? 'No validated data yet'
        ]);

        $validated = $request->validate([
            'exam_name' => 'required|string|max:255',
            'exam_description' => 'required|string',
            'passing_score' => 'required|integer|min:0|max:100',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        // Log the validated data
        Log::info('Validated exam data', [
            'validated_data' => $validated
        ]);

        try {
            // Log before creation attempt
            Log::info('Attempting to create exam', [
                'data' => $validated
            ]);

            $exam = Exam::create($validated);
            
            // Log the created exam
            Log::info('Exam creation result', [
                'exam' => $exam->toArray(),
                'exists' => $exam->exists,
                'wasRecentlyCreated' => $exam->wasRecentlyCreated
            ]);
            
            if (!$exam->exists) {
                Log::error('Failed to create exam', [
                    'data' => $validated,
                    'error' => 'Exam was not saved to database'
                ]);
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'حدث خطأ أثناء إنشاء الامتحان. يرجى المحاولة مرة أخرى.');
            }

            Log::info('Exam created successfully', [
                'exam_id' => $exam->exam_id,
                'exam_name' => $exam->exam_name
            ]);

            return redirect()->route('exams.index')
                ->with('success', 'تم إنشاء الامتحان بنجاح');
        } catch (\Exception $e) {
            Log::error('Error creating exam', [
                'data' => $validated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء الامتحان: ' . $e->getMessage());
        }
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