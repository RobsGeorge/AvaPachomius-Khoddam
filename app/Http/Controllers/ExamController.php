<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamResult;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::with(['module', 'course', 'schedules', 'results' => function ($query) {
            $query->where('user_id', Auth::id());
        }])->orderBy('exam_name')->get();

        return view('exams.index', compact('exams'));
    }

    public function dashboard()
    {
        $courses = Course::orderBy('title')->get(['course_id', 'title', 'year']);
        $modules = Module::with('courses')->orderBy('title')->get();
        $exams = Exam::with(['module', 'course', 'schedules', 'results'])->orderBy('exam_name')->get();

        return view('exams.dashboard', compact('exams', 'courses', 'modules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_name'        => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources'  => 'nullable|string',
            'exam_description' => 'nullable|string',
            'passing_score'    => 'nullable|integer|min:0|max:100',
            'course_id'        => 'required|exists:course,course_id',
            'module_id'        => 'required|exists:modules,module_id',
        ], [
            'module_id.required' => __('pages.module_required_for_exam'),
        ]);

        $this->assertModuleBelongsToCourse(
            (int) $validated['module_id'],
            (int) $validated['course_id']
        );

        Exam::create($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.exam_created_success'));
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'exam_name'        => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources'  => 'nullable|string',
            'exam_description' => 'nullable|string',
            'passing_score'    => 'nullable|integer|min:0|max:100',
            'course_id'        => 'required|exists:course,course_id',
            'module_id'        => 'required|exists:modules,module_id',
        ], [
            'module_id.required' => __('pages.module_required_for_exam'),
        ]);

        $this->assertModuleBelongsToCourse(
            (int) $validated['module_id'],
            (int) $validated['course_id']
        );

        $exam->update($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.exam_updated_success'));
    }

    public function scheduleExam(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date|after:now',
        ]);

        $exam->schedules()->create($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.schedule_added_success'));
    }

    public function updateResult(Request $request, ExamResult $result)
    {
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
        ]);

        $result->update($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.grade_updated_success'));
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();
        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.exam_deleted_success'));
    }

    public function adminDashboard()
    {
        // Get statistics
        $totalExams = Exam::count();
        $upcomingExams = ExamSchedule::where('scheduled_date', '>', now())->count();
        $completedExams = ExamSchedule::where('is_completed', true)->count();

        // Get upcoming exam schedules with related data
        $upcomingExamSchedules = ExamSchedule::with(['exam.module', 'exam.course', 'results'])
            ->where('scheduled_date', '>', now())
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        // Get recent results
        $recentResults = ExamResult::with(['user', 'exam.module', 'schedule'])
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

    private function assertModuleBelongsToCourse(int $moduleId, int $courseId): void
    {
        $linked = DB::table('course_module')
            ->where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'module_id' => __('pages.module_not_in_course'),
            ]);
        }
    }
} 