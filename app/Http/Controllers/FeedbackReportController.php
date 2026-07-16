<?php

namespace App\Http\Controllers;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\FeedbackSurveyService;
use Illuminate\Support\Facades\Auth;

class FeedbackReportController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveyService
    ) {}

    public function show(FeedbackSurvey $survey)
    {
        $this->authorizeReport($survey);

        $survey->load(['course', 'module', 'questions']);
        $aggregates = $this->surveyService->questionAggregates($survey);

        $submissions = FeedbackSubmission::query()
            ->with('user')
            ->where('survey_id', $survey->survey_id)
            ->latest('submitted_at')
            ->paginate(25);

        $enrolledCount = UserCourseRole::query()
            ->where('course_id', $survey->course_id)
            ->whereIn('role_id', Role::studentRoleIds())
            ->distinct('user_id')
            ->count('user_id');

        return view('feedback.admin.report', compact('survey', 'aggregates', 'submissions', 'enrolledCount'));
    }

    public function byQuestion(FeedbackSurvey $survey, FeedbackQuestion $question)
    {
        $this->authorizeReport($survey);
        abort_unless((int) $question->survey_id === (int) $survey->survey_id, 404);

        $answers = FeedbackAnswer::query()
            ->with(['submission.user'])
            ->where('question_id', $question->question_id)
            ->whereHas('submission', fn ($q) => $q->where('survey_id', $survey->survey_id))
            ->latest('answer_id')
            ->paginate(40);

        $aggregate = $this->surveyService->questionAggregates($survey)[$question->question_id] ?? null;

        return view('feedback.admin.report-question', compact('survey', 'question', 'answers', 'aggregate'));
    }

    public function byStudent(FeedbackSurvey $survey, User $user)
    {
        $this->authorizeReport($survey);

        $submission = FeedbackSubmission::query()
            ->with(['answers.question'])
            ->where('survey_id', $survey->survey_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        return view('feedback.admin.report-student', compact('survey', 'user', 'submission'));
    }

    private function authorizeReport(FeedbackSurvey $survey): void
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        if (Auth::user()->isAdmin()) {
            return;
        }

        abort_unless(
            Auth::user()->courses()->where('course.course_id', $survey->course_id)->exists(),
            403
        );
    }
}
