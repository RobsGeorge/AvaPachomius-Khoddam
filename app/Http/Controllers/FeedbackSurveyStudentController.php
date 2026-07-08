<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Services\FeedbackSurveyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackSurveyStudentController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveyService
    ) {}

    public function show(FeedbackSurvey $survey)
    {
        $this->authorizeStudentAccess($survey);

        $survey->load(['course', 'module', 'questions.session', 'questions.lecture', 'questions.targetUser']);

        $submission = FeedbackSubmission::query()
            ->with('answers')
            ->where('survey_id', $survey->survey_id)
            ->where('user_id', Auth::user()->user_id)
            ->first();

        if ($submission) {
            $answersByQuestion = $submission->answers->keyBy('question_id');

            return view('feedback.student.view', compact('survey', 'submission', 'answersByQuestion'));
        }

        if (! $survey->isOpen()) {
            return redirect()
                ->route('feedback.index')
                ->with('warning', __('pages.feedback_survey_closed'));
        }

        return view('feedback.student.form', compact('survey'));
    }

    public function store(Request $request, FeedbackSurvey $survey)
    {
        $this->authorizeStudentAccess($survey);

        $this->surveyService->submit(
            $survey->load('questions'),
            Auth::user(),
            $request->input('answers', [])
        );

        return redirect()
            ->route('feedback.surveys.show', $survey)
            ->with('success', __('pages.feedback_submitted_success'));
    }

    private function authorizeStudentAccess(FeedbackSurvey $survey): void
    {
        abort_unless(Auth::user()->isStudent(), 403);

        $enrolled = Auth::user()->courses()
            ->where('course.course_id', $survey->course_id)
            ->exists();

        abort_unless($enrolled, 403);
    }
}
