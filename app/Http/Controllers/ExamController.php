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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $query = Exam::with([
            'module',
            'course',
            'schedules',
            'results' => fn ($q) => $q->where('user_id', $userId),
            'attempts' => fn ($q) => $q->where('user_id', $userId),
        ])->orderBy('exam_name');

        if (Schema::hasColumn('exams', 'is_published')) {
            $query->where('is_published', true);
        }

        $currentCourse = current_course();
        if ($currentCourse) {
            $query->where('course_id', $currentCourse->course_id);
        }

        $exams = $query->get();

        return view('exams.index', compact('exams'));
    }

    public function dashboard()
    {
        $currentCourse = current_course();
        $courses = $currentCourse
            ? Course::whereKey($currentCourse->course_id)->get(['course_id', 'title', 'year'])
            : Course::orderBy('title')->get(['course_id', 'title', 'year']);

        $modulesQuery = Module::with('courses')->orderBy('title');
        if ($currentCourse) {
            $modulesQuery->whereHas('courses', fn ($q) => $q->where('course.course_id', $currentCourse->course_id));
        }
        $modules = $modulesQuery->get();

        $examsQuery = Exam::with(['module', 'course', 'schedules', 'results'])->orderBy('exam_name');
        if ($currentCourse) {
            $examsQuery->where('course_id', $currentCourse->course_id);
        }
        $exams = $examsQuery->get();

        return view('exams.dashboard', compact('exams', 'courses', 'modules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_name'        => 'required|string|max:255',
            'exam_type'        => 'required|in:exam,quiz',
            'delivery_mode'    => 'required|in:online,offline',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources'  => 'nullable|string',
            'exam_description' => 'nullable|string',
            'passing_score'    => 'nullable|integer|min:0|max:100',
            'shuffle_questions'=> 'nullable|boolean',
            'allow_late_entry' => 'nullable|boolean',
            'course_id'        => 'required|exists:course,course_id',
            'module_id'        => 'required|exists:modules,module_id',
        ], [
            'module_id.required' => __('pages.module_required_for_exam'),
        ]);

        $this->assertModuleBelongsToCourse(
            (int) $validated['module_id'],
            (int) $validated['course_id']
        );

        $validated['shuffle_questions'] = $request->boolean('shuffle_questions');
        $validated['allow_late_entry'] = $request->boolean('allow_late_entry', true);
        $validated['is_published'] = false;

        Exam::create($validated);

        return redirect()->route('exams.dashboard')
            ->with('success', __('pages.exam_created_success'));
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'exam_name'        => 'required|string|max:255',
            'exam_type'        => 'required|in:exam,quiz',
            'delivery_mode'    => 'required|in:online,offline',
            'duration_minutes' => 'required|integer|min:1',
            'study_resources'  => 'nullable|string',
            'exam_description' => 'nullable|string',
            'passing_score'    => 'nullable|integer|min:0|max:100',
            'shuffle_questions'=> 'nullable|boolean',
            'allow_late_entry' => 'nullable|boolean',
            'course_id'        => 'required|exists:course,course_id',
            'module_id'        => 'required|exists:modules,module_id',
        ], [
            'module_id.required' => __('pages.module_required_for_exam'),
        ]);

        $this->assertModuleBelongsToCourse(
            (int) $validated['module_id'],
            (int) $validated['course_id']
        );

        $validated['shuffle_questions'] = $request->boolean('shuffle_questions');
        $validated['allow_late_entry'] = $request->boolean('allow_late_entry', true);

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