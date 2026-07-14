<?php

namespace App\Services;

use App\Mail\CourseApplicationReviewMail;
use App\Models\CourseApplication;
use App\Models\CourseApplicationFieldReview;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationReviewTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CourseApplicationMailService
{
    public function __construct(
        private EmailLocaleResolver $localeResolver,
        private CommunicationLogService $communicationLogs,
    ) {}

    public function ensureDefaults(?int $courseId = null): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (CourseApplicationReviewTemplate::keys() as $key) {
                CourseApplicationReviewTemplate::query()->firstOrCreate(
                    [
                        'course_id' => $courseId,
                        'template_key' => $key,
                        'locale' => $locale,
                    ],
                    [
                        'subject' => __("course_applications.email_subjects.{$key}", [], $locale),
                        'body_html' => __("course_applications.email_bodies.{$key}", [], $locale),
                    ]
                );
            }
        }
    }

    /** @param array<string, string> $extra */
    public function send(User $user, string $templateKey, CourseApplication $application, array $extra = []): void
    {
        if (! filled($user->email)) {
            $this->communicationLogs->markSkipped(
                $this->communicationLogs->record([
                    'user' => $user,
                    'channel' => \App\Models\CommunicationLog::CHANNEL_EMAIL,
                    'subject' => $templateKey,
                    'course_id' => $application->course_id,
                    'related_type' => CourseApplication::class,
                    'related_id' => $application->id,
                    'metadata' => ['template' => $templateKey, 'family' => 'course_application'],
                ]),
                __('communications.missing_email')
            );

            return;
        }

        $this->ensureDefaults($application->course_id);
        $this->ensureDefaults(null);

        $locale = $this->localeResolver->forRecipient(
            $user,
            EmailTemplateCatalog::FAMILY_COURSE_APPLICATION,
            $templateKey,
            $application->course_id
        );
        $template = CourseApplicationReviewTemplate::query()
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->where(function ($q) use ($application) {
                $q->where('course_id', $application->course_id)
                    ->orWhereNull('course_id');
            })
            ->orderByRaw('course_id IS NULL')
            ->first();

        if (! $template) {
            return;
        }

        $log = $this->communicationLogs->record([
            'user' => $user,
            'channel' => \App\Models\CommunicationLog::CHANNEL_EMAIL,
            'subject' => $template->subject,
            'body_preview' => $template->body_html,
            'course_id' => $application->course_id,
            'related_type' => CourseApplication::class,
            'related_id' => $application->id,
            'metadata' => ['template' => $templateKey, 'family' => 'course_application'],
        ]);

        try {
            Mail::to($user->email)->send(
                (new CourseApplicationReviewMail(
                    $user,
                    $template,
                    $this->buildReplacements($user, $application, $extra)
                ))->with(['trackingToken' => $log?->tracking_token])
            );
            $this->communicationLogs->markSent($log);
        } catch (\Throwable $e) {
            $this->communicationLogs->markFailed($log, $e->getMessage());
            Log::warning('Course application review email failed', [
                'user_id' => $user->user_id,
                'application_id' => $application->id,
                'template' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function buildFieldsTable(CourseApplication $application): string
    {
        $application->loadMissing(['fieldReviews', 'form.steps.fields']);
        $labels = $this->fieldLabels($application);
        $rows = [];

        foreach ($application->fieldReviews as $review) {
            if ($review->status !== CourseApplicationFieldReview::STATUS_REJECTED) {
                continue;
            }

            $label = $labels[$review->field_key] ?? $review->field_key;
            $value = $this->formatSnapshotValue($application->snapshot[$review->field_key] ?? null);
            $comment = $review->comment ? ' — '.$review->comment : '';
            $rows[] = "<li><strong>{$label}</strong>: {$value}{$comment}</li>";
        }

        if ($rows === []) {
            return '';
        }

        return '<ul>'.implode('', $rows).'</ul>';
    }

    /** @return array<string, string> */
    private function fieldLabels(CourseApplication $application): array
    {
        $labels = [];
        $form = $application->form;

        if (! $form) {
            return $labels;
        }

        foreach ($form->steps as $step) {
            foreach ($step->fields as $field) {
                $labels[$field->field_key] = $field->label;
            }
        }

        return $labels;
    }

    private function formatSnapshotValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value) ?: '—';
        }

        if (is_bool($value)) {
            return $value ? __('course_applications.yes') : __('course_applications.no');
        }

        if (is_string($value) && str_starts_with($value, 'course-applications/')) {
            return basename($value);
        }

        return filled($value) ? (string) $value : '—';
    }

    /** @param array<string, string> $extra @return array<string, string> */
    private function buildReplacements(User $user, CourseApplication $application, array $extra): array
    {
        return array_merge([
            'name' => $user->first_name ?: $user->displayName(),
            'course' => $application->course?->title ?? '',
            'note' => $extra['note'] ?? '',
            'fields_table' => $extra['fields_table'] ?? '',
            'portal_url' => route('courses.application.status', $application->course_id),
            'correction_url' => route('courses.application.edit', $application->course_id),
        ], $extra);
    }
}
