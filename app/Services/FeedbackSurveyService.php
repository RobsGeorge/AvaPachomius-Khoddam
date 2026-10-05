<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
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
            ->with([
                'course',
                'module',
                'blockedExam',
                'blockedProjectAssessment',
                'submissions' => fn ($q) => $q->where('user_id', $user->user_id),
            ])
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [FeedbackSurvey::STATUS_OPEN, FeedbackSurvey::STATUS_CLOSED])
            ->orderByDesc('opened_at')
            ->orderByDesc('survey_id')
            ->get();
    }

    public function surveysForAdmin(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = FeedbackSurvey::query()
            ->with(['course', 'module', 'creator', 'blockedExam', 'blockedProjectAssessment'])
            ->withCount('submissions')
            ->orderByDesc('survey_id');

        if (! $user->isAdmin()) {
            $courseIds = $user->courses()->pluck('course.course_id');
            $query->whereIn('course_id', $courseIds);
        }

        return $query->get();
    }

    /**
     * Tell enrolled students a survey is open: in-portal notification plus a
     * course announcement that deep-links to the form.
     */
    public function releaseToStudents(FeedbackSurvey $survey, User $publisher): void
    {
        $this->notifyStudents($survey);
        $this->ensureStudentAnnouncement($survey, $publisher);
    }

    public function notifyStudents(FeedbackSurvey $survey): void
    {
        $course = $survey->course ?? Course::find($survey->course_id);
        if (! $course) {
            return;
        }

        $students = app(StudentRosterService::class)->enrolledStudents($course);
        $generator = app(NotificationGeneratorService::class);
        $preferences = app(NotificationPreferenceService::class);
        $path = route('feedback.surveys.show', $survey, false);
        $title = __('notifications.generated.feedback_survey_open_title', ['title' => $survey->title]);
        $moduleLabel = $survey->module?->title ?: ($course->title ?? '');
        $body = __('notifications.generated.feedback_survey_open_body', [
            'course' => $course->title,
            'module' => $moduleLabel,
        ]);

        foreach ($students as $student) {
            $preferences->ensureDefaults($student);
            $generator->createOrUpdate(
                $student,
                'feedback_survey_open',
                $title,
                $body,
                $path,
                'feedback_survey',
                (int) $survey->survey_id,
                UserNotification::PRIORITY_HIGH,
                [
                    'course_id' => $survey->course_id,
                    'module_id' => $survey->module_id,
                    'survey_id' => $survey->survey_id,
                ],
                "feedback_survey_open:{$survey->survey_id}:user:{$student->user_id}",
                false
            );
        }
    }

    public function ensureStudentAnnouncement(FeedbackSurvey $survey, User $publisher): void
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasColumn('announcements', 'survey_id')) {
            return;
        }

        $existing = Announcement::query()->where('survey_id', $survey->survey_id)->first();
        $announcements = app(AnnouncementService::class);

        if ($existing) {
            if ($existing->isDraft()) {
                $announcements->publish($existing, $publisher);
            }

            return;
        }

        $body = trim((string) $survey->description);
        if ($body === '') {
            $body = __('pages.feedback_survey_open_announcement_body', ['title' => $survey->title]);
        }

        $draft = $announcements->createDraft($publisher, [
            'title' => $survey->title,
            'body' => $body,
            'target_mode' => Announcement::TARGET_COURSE,
            'course_id' => $survey->course_id,
            'survey_id' => $survey->survey_id,
            'channels' => [
                Announcement::CHANNEL_HOMEPAGE => true,
                Announcement::CHANNEL_BANNER_DISMISSIBLE => true,
            ],
        ]);

        $announcements->publish($draft, $publisher);
    }

    public function staffForCourse(int $courseId): \Illuminate\Support\Collection
    {
        return UserCourseRole::query()
            ->with(['user', 'role'])
            ->where('course_id', $courseId)
            ->whereIn('role_id', Role::staffRoleIds())
            ->get()
            ->map(fn ($ucr) => $ucr->user)
            ->filter()
            ->unique('user_id')
            ->values();
    }

    public function submit(FeedbackSurvey $survey, User $user, array $answers, array $otherTexts = []): FeedbackSubmission
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
        $validated = $this->validateAnswers($questions, $answers, $otherTexts);

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
                    'value' => is_array($value) ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE) : (string) $value,
                ]);
            }

            return $submission->load('answers.question');
        });
    }

    /**
     * @param  iterable<int, FeedbackQuestion>  $questions
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherTexts
     * @return array<string, mixed>
     */
    public function validateAnswers($questions, array $answers, array $otherTexts = []): array
    {
        $rules = $this->validationRules($questions);
        $validator = validator([
            'answers' => $answers,
            'answers_other' => $otherTexts,
        ], $rules);

        $validator->after(function ($validator) use ($questions, $answers, $otherTexts) {
            $this->validateMcqAnswers($validator, $questions, $answers, $otherTexts);
        });

        $validated = $validator->validate();
        $normalized = $validated['answers'] ?? [];

        foreach ($questions as $question) {
            if ($question->question_type !== FeedbackQuestion::TYPE_MCQ) {
                continue;
            }

            $key = (string) $question->question_id;
            $normalized[$key] = $this->normalizeMcqAnswer(
                $question,
                $answers[$question->question_id] ?? $answers[$key] ?? null,
                $otherTexts[$question->question_id] ?? $otherTexts[$key] ?? null
            );
        }

        return $normalized;
    }

    public function validationRules($questions): array
    {
        $rules = [
            'answers' => 'nullable|array',
            'answers_other' => 'nullable|array',
        ];

        foreach ($questions as $question) {
            $key = 'answers.'.$question->question_id;
            $rule = $question->is_required ? 'required' : 'nullable';

            if ($question->question_type === FeedbackQuestion::TYPE_MCQ) {
                $choiceRules = $this->mcqChoiceRules($question);
                if ($question->allowsMultiple()) {
                    $rules[$key] = ['nullable', 'array'];
                    $rules[$key.'.*'] = array_merge(['string'], $choiceRules);
                } else {
                    $rules[$key] = array_merge(['nullable', 'string'], $choiceRules);
                }
                $rules['answers_other.'.$question->question_id] = ['nullable', 'string', 'max:1000'];
                continue;
            }

            $rules[$key] = match ($question->question_type) {
                FeedbackQuestion::TYPE_RATING => [$rule, 'integer', 'min:1', 'max:'.$question->ratingMax()],
                FeedbackQuestion::TYPE_SLIDER => [$rule, 'integer', 'min:'.$question->sliderMin(), 'max:'.$question->sliderMax()],
                FeedbackQuestion::TYPE_TEXT => array_merge([$rule, 'string', 'max:5000']),
                default => [$rule, 'string', 'max:5000'],
            };
        }

        return $rules;
    }

    private function mcqChoiceRules(FeedbackQuestion $question): array
    {
        $choices = $question->allowedChoiceValues();

        if ($choices === []) {
            return [];
        }

        return [Rule::in($choices)];
    }

    /**
     * @param  iterable<int, FeedbackQuestion>  $questions
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $otherTexts
     */
    private function validateMcqAnswers($validator, $questions, array $answers, array $otherTexts): void
    {
        foreach ($questions as $question) {
            if ($question->question_type !== FeedbackQuestion::TYPE_MCQ) {
                continue;
            }

            $key = (string) $question->question_id;
            $raw = $answers[$question->question_id] ?? $answers[$key] ?? null;
            $other = trim((string) ($otherTexts[$question->question_id] ?? $otherTexts[$key] ?? ''));
            $selected = $this->selectedMcqValues($raw);
            $hasListedChoice = $this->hasListedMcqChoice($question, $selected);
            $wantsOther = in_array(FeedbackQuestion::OTHER_CHOICE, $selected, true) || $other !== '';

            if ($question->is_required && ! $hasListedChoice && $other === '') {
                $validator->errors()->add('answers.'.$key, __('validation.required'));
            }

            if ($wantsOther && ! $question->allowsOther()) {
                $validator->errors()->add('answers.'.$key, __('pages.feedback_other_not_allowed'));
            }

            if ($wantsOther && $other === '') {
                $validator->errors()->add('answers_other.'.$key, __('pages.feedback_other_required'));
            }

            if (! $question->allowsMultiple() && count($this->listedMcqChoices($question, $selected)) > 1) {
                $validator->errors()->add('answers.'.$key, __('pages.feedback_single_choice_only'));
            }
        }
    }

    /**
     * @return list<string>|string|null
     */
    private function normalizeMcqAnswer(FeedbackQuestion $question, mixed $raw, mixed $otherText): mixed
    {
        $selected = $this->selectedMcqValues($raw);
        $other = trim((string) $otherText);
        $choices = $this->listedMcqChoices($question, $selected);

        if ($other !== '' && $question->allowsOther()) {
            $choices[] = $other;
        }

        $choices = array_values(array_unique($choices));

        if ($choices === []) {
            return null;
        }

        return $question->allowsMultiple() ? $choices : $choices[0];
    }

    /**
     * @return list<string>
     */
    private function selectedMcqValues(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                $raw
            )));
        }

        if ($raw === null || $raw === '') {
            return [];
        }

        return [trim((string) $raw)];
    }

    /**
     * @param  list<string>  $selected
     * @return list<string>
     */
    private function listedMcqChoices(FeedbackQuestion $question, array $selected): array
    {
        $allowed = $question->choices();

        return array_values(array_filter(
            $selected,
            static fn (string $value) => $value !== FeedbackQuestion::OTHER_CHOICE && in_array($value, $allowed, true)
        ));
    }

    /**
     * @param  list<string>  $selected
     */
    private function hasListedMcqChoice(FeedbackQuestion $question, array $selected): bool
    {
        return $this->listedMcqChoices($question, $selected) !== [];
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
                'distribution' => $this->distribution($answers, $question),
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

    private function distribution($answers, FeedbackQuestion $question): array
    {
        $flat = collect();

        foreach ($answers as $value) {
            if ($question->question_type === FeedbackQuestion::TYPE_MCQ && is_string($value) && str_starts_with(ltrim($value), '[')) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $flat->push((string) $item);
                    }
                    continue;
                }
            }

            $flat->push((string) $value);
        }

        return $flat
            ->countBy()
            ->sortKeys()
            ->map(fn ($count, $value) => ['value' => $value, 'count' => $count])
            ->values()
            ->all();
    }
}
