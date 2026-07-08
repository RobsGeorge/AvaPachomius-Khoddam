<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSurvey;
use App\Models\Lecture;
use App\Models\Module;
use App\Models\Session;
use App\Services\FeedbackSurveyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FeedbackSurveyAdminController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveyService
    ) {}

    public function create(Request $request)
    {
        $courses = $this->accessibleCourses();
        $selectedCourse = $request->query('course_id');
        $selectedModule = $request->query('module_id');

        return view('feedback.admin.create', compact('courses', 'selectedCourse', 'selectedModule'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'required|exists:course,course_id',
            'module_id' => 'required|exists:modules,module_id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'due_at' => 'nullable|date',
            'is_mandatory' => 'boolean',
        ]);

        $this->authorizeCourse((int) $data['course_id']);

        $survey = FeedbackSurvey::create([
            'course_id' => $data['course_id'],
            'module_id' => $data['module_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'created_by_user_id' => Auth::user()->user_id,
            'status' => FeedbackSurvey::STATUS_DRAFT,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'due_at' => $data['due_at'] ?? null,
        ]);

        return redirect()
            ->route('feedback.surveys.edit', $survey)
            ->with('success', __('pages.feedback_survey_created'));
    }

    public function edit(FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        $survey->load(['questions.session', 'questions.lecture', 'questions.targetUser', 'course', 'module']);

        $course = $survey->course;
        $sessions = Session::where('course_id', $course->course_id)
            ->where(function ($q) use ($survey) {
                $q->where('module_id', $survey->module_id)
                    ->orWhereNull('module_id');
            })
            ->orderBy('session_date')
            ->get();

        $lectures = Lecture::where('module_id', $survey->module_id)
            ->orderBy('order_index')
            ->get();

        $staff = $this->surveyService->staffForCourse((int) $survey->course_id);

        return view('feedback.admin.builder', compact('survey', 'sessions', 'lectures', 'staff'));
    }

    public function update(Request $request, FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'due_at' => 'nullable|date',
            'is_mandatory' => 'boolean',
        ]);

        $survey->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'due_at' => $data['due_at'] ?? null,
        ]);

        return back()->with('success', __('pages.feedback_survey_saved'));
    }

    public function storeQuestion(Request $request, FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        $data = $request->validate([
            'question_type' => ['required', Rule::in([
                FeedbackQuestion::TYPE_RATING,
                FeedbackQuestion::TYPE_SLIDER,
                FeedbackQuestion::TYPE_MCQ,
                FeedbackQuestion::TYPE_TEXT,
            ])],
            'scope' => ['required', Rule::in([
                FeedbackQuestion::SCOPE_GENERAL,
                FeedbackQuestion::SCOPE_SESSION,
                FeedbackQuestion::SCOPE_LECTURE,
                FeedbackQuestion::SCOPE_INSTRUCTOR,
            ])],
            'label' => 'required|string|max:500',
            'help_text' => 'nullable|string|max:1000',
            'is_required' => 'boolean',
            'session_id' => 'nullable|exists:session,session_id',
            'lecture_id' => 'nullable|exists:lectures,lecture_id',
            'target_user_id' => 'nullable|exists:user,user_id',
            'choices' => 'nullable|string|max:2000',
            'min' => 'nullable|integer|min:0|max:100',
            'max' => 'nullable|integer|min:1|max:100',
            'max_rating' => 'nullable|integer|min:3|max:10',
        ]);

        $config = $this->buildConfig($data);

        $nextOrder = (int) $survey->questions()->max('order_index') + 1;

        $survey->questions()->create([
            'question_type' => $data['question_type'],
            'scope' => $data['scope'],
            'session_id' => $data['scope'] === FeedbackQuestion::SCOPE_SESSION ? ($data['session_id'] ?? null) : null,
            'lecture_id' => $data['scope'] === FeedbackQuestion::SCOPE_LECTURE ? ($data['lecture_id'] ?? null) : null,
            'target_user_id' => $data['scope'] === FeedbackQuestion::SCOPE_INSTRUCTOR ? ($data['target_user_id'] ?? null) : null,
            'label' => $data['label'],
            'help_text' => $data['help_text'] ?? null,
            'order_index' => $nextOrder,
            'is_required' => $request->boolean('is_required', true),
            'config' => $config,
        ]);

        return back()->with('success', __('pages.feedback_question_added'));
    }

    public function destroyQuestion(FeedbackSurvey $survey, FeedbackQuestion $question)
    {
        $this->authorizeSurvey($survey);
        abort_unless((int) $question->survey_id === (int) $survey->survey_id, 404);

        $question->delete();

        return back()->with('success', __('pages.feedback_question_removed'));
    }

    public function publish(FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        if ($survey->questions()->count() === 0) {
            return back()->withErrors(['survey' => __('pages.feedback_no_questions')]);
        }

        $survey->update([
            'status' => FeedbackSurvey::STATUS_OPEN,
            'opened_at' => now(),
            'closed_at' => null,
        ]);

        return back()->with('success', __('pages.feedback_survey_published'));
    }

    public function close(FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        $survey->update([
            'status' => FeedbackSurvey::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return back()->with('success', __('pages.feedback_survey_closed_admin'));
    }

    public function destroy(FeedbackSurvey $survey)
    {
        $this->authorizeSurvey($survey);

        if ($survey->submissions()->exists()) {
            return back()->withErrors(['survey' => __('pages.feedback_cannot_delete_with_responses')]);
        }

        $survey->delete();

        return redirect()
            ->route('feedback.index')
            ->with('success', __('pages.feedback_survey_deleted'));
    }

    private function buildConfig(array $data): array
    {
        $config = [];

        if ($data['question_type'] === FeedbackQuestion::TYPE_MCQ) {
            $choices = array_values(array_filter(array_map('trim', explode("\n", $data['choices'] ?? ''))));
            $config['choices'] = $choices;
        }

        if ($data['question_type'] === FeedbackQuestion::TYPE_SLIDER) {
            $config['min'] = (int) ($data['min'] ?? 1);
            $config['max'] = (int) ($data['max'] ?? 10);
        }

        if ($data['question_type'] === FeedbackQuestion::TYPE_RATING) {
            $config['max_rating'] = (int) ($data['max_rating'] ?? 5);
        }

        return $config;
    }

    private function accessibleCourses()
    {
        if (Auth::user()->isAdmin()) {
            return Course::with('modules')->orderBy('title')->get();
        }

        return Auth::user()->courses()->with('modules')->orderBy('title')->get();
    }

    private function authorizeCourse(int $courseId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        abort_unless(
            Auth::user()->courses()->where('course.course_id', $courseId)->exists(),
            403
        );
    }

    private function authorizeSurvey(FeedbackSurvey $survey): void
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);
        $this->authorizeCourse((int) $survey->course_id);
    }
}
