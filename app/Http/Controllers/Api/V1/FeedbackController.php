<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Services\FeedbackSurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveys,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isStudent(), 403);

        $rows = $this->surveys->surveysForStudent($user);

        return response()->json([
            'data' => $rows->map(function (FeedbackSurvey $survey) {
                return [
                    'survey_id' => $survey->survey_id,
                    'title' => $survey->title,
                    'status' => $survey->status,
                    'course_id' => $survey->course_id,
                    'module_id' => $survey->module_id,
                    'is_open' => $survey->isOpen(),
                    'submitted' => $survey->submissions->isNotEmpty(),
                    'opened_at' => $survey->opened_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, FeedbackSurvey $survey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeStudentAccess($user, $survey);

        $survey->load(['questions', 'course', 'module']);
        $submission = FeedbackSubmission::query()
            ->with('answers')
            ->where('survey_id', $survey->survey_id)
            ->where('user_id', $user->user_id)
            ->first();

        return response()->json([
            'data' => [
                'survey_id' => $survey->survey_id,
                'title' => $survey->title,
                'status' => $survey->status,
                'is_open' => $survey->isOpen(),
                'questions' => $survey->questions->map(fn ($q) => [
                    'question_id' => $q->question_id ?? $q->getKey(),
                    'prompt' => $q->prompt ?? $q->question_text ?? null,
                    'type' => $q->type ?? $q->question_type ?? null,
                    'required' => (bool) ($q->required ?? false),
                    'options' => $q->options ?? null,
                ])->values(),
                'submission' => $submission ? [
                    'submission_id' => $submission->submission_id ?? $submission->getKey(),
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'answers' => $submission->answers->map(fn ($a) => [
                        'question_id' => $a->question_id,
                        'value' => $a->value ?? $a->answer_text ?? $a->answer_value ?? null,
                    ])->values(),
                ] : null,
            ],
        ]);
    }

    public function submit(Request $request, FeedbackSurvey $survey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeStudentAccess($user, $survey);

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        try {
            $submission = $this->surveys->submit($survey->load('questions'), $user, $data['answers']);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'data' => [
                'submission_id' => $submission->submission_id ?? $submission->getKey(),
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function authorizeStudentAccess(User $user, FeedbackSurvey $survey): void
    {
        abort_unless($user->isStudent(), 403);

        $enrolled = $user->courses()
            ->where('course.course_id', $survey->course_id)
            ->exists();

        abort_unless($enrolled, 403);
    }
}
