<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeedbackSurveyService
{
    public function surveysForStudent(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $courseIds = $user->courses()->pluck('course.course_id');

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return FeedbackSurvey::query()
            ->with(['course', 'module', 'submissions' => fn ($q) => $q->where('user_id', $user->user_id)])
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [FeedbackSurvey::STATUS_OPEN, FeedbackSurvey::STATUS_CLOSED])
            ->orderByDesc('opened_at')
            ->orderByDesc('survey_id')
            ->get();
    }

    public function surveysForAdmin(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = FeedbackSurvey::query()
            ->with(['course', 'module', 'creator'])
            ->withCount('submissions')
            ->orderByDesc('survey_id');

        if (! $user->isAdmin()) {
            $courseIds = $user->courses()->pluck('course.course_id');
            $query->whereIn('course_id', $courseIds);
        }

        return $query->get();
    }

    public function staffForCourse(int $courseId): \Illuminate\Support\Collection
    {
        return UserCourseRole::query()
            ->with(['user', 'role'])
            ->where('course_id', $courseId)
            ->whereHas('role', function ($q) {
                $q->whereRaw('LOWER(role_name) IN (?, ?)', ['admin', 'instructor']);
            })
            ->get()
            ->map(fn ($ucr) => $ucr->user)
            ->filter()
            ->unique('user_id')
            ->values();
    }

    public function submit(FeedbackSurvey $survey, User $user, array $answers): FeedbackSubmission
    {
        if (! $survey->isOpen()) {
            throw ValidationException::withMessages([
                'survey' => [__('pages.feedback_survey_not_open')],
            ]);
        }

        if ($survey->submissions()->where('user_id', $user->user_id)->exists()) {
            throw ValidationException::withMessages([
                'survey' => [__('pages.feedback_already_submitted')],
            ]);
        }

        $questions = $survey->questions;
        $rules = $this->validationRules($questions);
        $validated = validator(['answers' => $answers], $rules)->validate()['answers'] ?? [];

        return DB::transaction(function () use ($survey, $user, $questions, $validated) {
            $submission = FeedbackSubmission::create([
                'survey_id' => $survey->survey_id,
                'user_id' => $user->user_id,
                'submitted_at' => now(),
            ]);

            foreach ($questions as $question) {
                $key = (string) $question->question_id;
                $value = $validated[$key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                FeedbackAnswer::create([
                    'submission_id' => $submission->submission_id,
                    'question_id' => $question->question_id,
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                ]);
            }

            return $submission->load('answers.question');
        });
    }

    public function validationRules($questions): array
    {
        $rules = ['answers' => 'required|array'];

        foreach ($questions as $question) {
            $key = 'answers.'.$question->question_id;
            $rule = $question->is_required ? 'required' : 'nullable';

            $rules[$key] = match ($question->question_type) {
                FeedbackQuestion::TYPE_RATING => [$rule, 'integer', 'min:1', 'max:'.$question->ratingMax()],
                FeedbackQuestion::TYPE_SLIDER => [$rule, 'integer', 'min:'.$question->sliderMin(), 'max:'.$question->sliderMax()],
                FeedbackQuestion::TYPE_MCQ => array_merge([$rule, 'string'], $this->mcqChoiceRules($question)),
                FeedbackQuestion::TYPE_TEXT => array_merge([$rule, 'string', 'max:5000']),
                default => [$rule, 'string', 'max:5000'],
            };
        }

        return $rules;
    }

    private function mcqChoiceRules(FeedbackQuestion $question): array
    {
        $choices = $question->choices();

        if ($choices === []) {
            return [];
        }

        return ['in:'.implode(',', array_map(fn ($c) => str_replace(',', '\\,', $c), $choices))];
    }

    public function questionAggregates(FeedbackSurvey $survey): array
    {
        $result = [];

        foreach ($survey->questions as $question) {
            $answers = FeedbackAnswer::query()
                ->whereHas('submission', fn ($q) => $q->where('survey_id', $survey->survey_id))
                ->where('question_id', $question->question_id)
                ->pluck('value');

            $result[$question->question_id] = [
                'question' => $question,
                'count' => $answers->count(),
                'answers' => $answers->all(),
                'numeric_avg' => $this->numericAverage($question, $answers),
                'distribution' => $this->distribution($answers),
            ];
        }

        return $result;
    }

    private function numericAverage(FeedbackQuestion $question, $answers): ?float
    {
        if (! in_array($question->question_type, [FeedbackQuestion::TYPE_RATING, FeedbackQuestion::TYPE_SLIDER], true)) {
            return null;
        }

        $nums = $answers->map(fn ($v) => is_numeric($v) ? (float) $v : null)->filter();

        return $nums->isEmpty() ? null : round($nums->avg(), 2);
    }

    private function distribution($answers): array
    {
        return $answers
            ->countBy()
            ->sortKeys()
            ->map(fn ($count, $value) => ['value' => $value, 'count' => $count])
            ->values()
            ->all();
    }
}
