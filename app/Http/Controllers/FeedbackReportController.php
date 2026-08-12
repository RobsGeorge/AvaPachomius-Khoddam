<?php

namespace App\Http\Controllers;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Role;
use App\Models\UserCourseRole;
use App\Services\FeedbackIdentityRevealService;
use App\Services\FeedbackSurveyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackReportController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveyService,
        private FeedbackIdentityRevealService $revealService,
    ) {}

    public function show(FeedbackSurvey $survey)
    {
        $this->authorizeReport($survey);

        $survey->load(['course', 'module', 'questions']);
        $aggregates = $this->surveyService->questionAggregates($survey);
        $viewer = Auth::user();

        $submissions = FeedbackSubmission::query()
            ->where('survey_id', $survey->survey_id)
            ->latest('submitted_at')
            ->paginate(25);

        $submissionIds = $submissions->getCollection()->pluck('submission_id');
        $activeReveals = $this->revealService->activeRevealsForViewer($viewer, $submissionIds);
        $pendingRequests = $this->revealService->pendingRequestsForViewer($viewer, $submissionIds)
            ->keyBy('submission_id');

        // Load user only for submissions the viewer is allowed to identify.
        $revealedUserIds = $activeReveals->keys()
            ->map(fn ($id) => $submissions->getCollection()->firstWhere('submission_id', $id)?->user_id)
            ->filter()
            ->values();

        if ($revealedUserIds->isNotEmpty()) {
            $submissions->getCollection()->loadMissing(['user' => function ($q) use ($revealedUserIds) {
                $q->whereIn('user_id', $revealedUserIds);
            }]);
        }

        $enrolledCount = UserCourseRole::query()
            ->where('course_id', $survey->course_id)
            ->whereIn('role_id', Role::studentRoleIds())
            ->distinct('user_id')
            ->count('user_id');

        return view('feedback.admin.report', compact(
            'survey',
            'aggregates',
            'submissions',
            'enrolledCount',
            'activeReveals',
            'pendingRequests'
        ));
    }

    public function byQuestion(FeedbackSurvey $survey, FeedbackQuestion $question)
    {
        $this->authorizeReport($survey);
        abort_unless((int) $question->survey_id === (int) $survey->survey_id, 404);

        $viewer = Auth::user();

        $answers = FeedbackAnswer::query()
            ->with('submission')
            ->where('question_id', $question->question_id)
            ->whereHas('submission', fn ($q) => $q->where('survey_id', $survey->survey_id))
            ->latest('answer_id')
            ->paginate(40);

        $submissionIds = $answers->getCollection()->pluck('submission_id')->unique()->values();
        $activeReveals = $this->revealService->activeRevealsForViewer($viewer, $submissionIds);
        $pendingRequests = $this->revealService->pendingRequestsForViewer($viewer, $submissionIds);

        $pendingByAnswer = $pendingRequests
            ->filter(fn ($r) => $r->answer_id !== null)
            ->keyBy('answer_id');
        $pendingBySubmission = $pendingRequests
            ->filter(fn ($r) => $r->answer_id === null)
            ->keyBy('submission_id');

        $revealedSubmissionIds = $activeReveals->keys();
        if ($revealedSubmissionIds->isNotEmpty()) {
            $answers->getCollection()->loadMissing(['submission.user']);
        }

        $aggregate = $this->surveyService->questionAggregates($survey)[$question->question_id] ?? null;

        return view('feedback.admin.report-question', compact(
            'survey',
            'question',
            'answers',
            'aggregate',
            'activeReveals',
            'pendingByAnswer',
            'pendingBySubmission'
        ));
    }

    public function bySubmission(FeedbackSurvey $survey, FeedbackSubmission $submission)
    {
        $this->authorizeReport($survey);
        abort_unless((int) $submission->survey_id === (int) $survey->survey_id, 404);

        $viewer = Auth::user();
        $canSeeIdentity = $this->revealService->viewerCanSeeIdentity($viewer, $submission);
        if ($canSeeIdentity) {
            $submission->load(['user', 'answers.question']);
        } else {
            $submission->load(['answers.question']);
        }

        $pendingRequest = $this->revealService->pendingRequestsForViewer(
            $viewer,
            collect([$submission->submission_id])
        )->first();

        $identityLabel = $canSeeIdentity
            ? ($submission->user?->displayName() ?? '—')
            : $this->revealService->anonymousLabel($submission);

        return view('feedback.admin.report-student', compact(
            'survey',
            'submission',
            'canSeeIdentity',
            'pendingRequest',
            'identityLabel'
        ));
    }

    /** @deprecated Use bySubmission; kept for old bookmarks, redirects anonymously. */
    public function byStudent(FeedbackSurvey $survey, int $user)
    {
        $this->authorizeReport($survey);

        $submission = FeedbackSubmission::query()
            ->where('survey_id', $survey->survey_id)
            ->where('user_id', $user)
            ->firstOrFail();

        return redirect()->route('feedback.surveys.report.submission', [$survey, $submission]);
    }

    public function requestReveal(Request $request, FeedbackSurvey $survey, FeedbackSubmission $submission)
    {
        $this->authorizeReport($survey);
        abort_unless((int) $submission->survey_id === (int) $survey->survey_id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
            'answer_id' => 'nullable|integer|exists:feedback_answers,answer_id',
        ]);

        $answer = null;
        if (! empty($data['answer_id'])) {
            $answer = FeedbackAnswer::query()
                ->where('answer_id', $data['answer_id'])
                ->where('submission_id', $submission->submission_id)
                ->firstOrFail();
        }

        $this->revealService->requestReveal(
            Auth::user(),
            $survey,
            $submission,
            $data['reason'],
            $answer
        );

        return back()->with('success', __('pages.feedback_reveal_requested'));
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
