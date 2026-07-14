<?php

namespace App\Services;

use App\Mail\RegistrationReviewMail;
use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use App\Models\RegistrationReviewTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationReviewMailService
{
    public function __construct(
        private EmailLocaleResolver $localeResolver,
    ) {}

    public function ensureDefaults(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (RegistrationReviewTemplate::keys() as $key) {
                RegistrationReviewTemplate::query()->firstOrCreate(
                    ['template_key' => $key, 'locale' => $locale],
                    [
                        'subject' => __("registration_review.email_subjects.{$key}", [], $locale),
                        'body_html' => __("registration_review.email_bodies.{$key}", [], $locale),
                    ]
                );
            }
        }
    }

    /** @param array<string, string> $extra */
    public function send(User $user, string $templateKey, array $extra = []): void
    {
        if (! filled($user->email)) {
            return;
        }

        $this->ensureDefaults();

        $locale = $this->localeResolver->forRecipient(
            $user,
            EmailTemplateCatalog::FAMILY_REGISTRATION_REVIEW,
            $templateKey
        );
        $template = RegistrationReviewTemplate::query()
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->first()
            ?? RegistrationReviewTemplate::query()
                ->where('template_key', $templateKey)
                ->where('locale', 'ar')
                ->first();

        if (! $template) {
            return;
        }

        try {
            Mail::to($user->email)->send(new RegistrationReviewMail(
                $user,
                $template,
                $this->buildReplacements($user, $extra)
            ));
        } catch (\Throwable $e) {
            Log::warning('Registration review email failed', [
                'user_id' => $user->user_id,
                'template' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function buildFieldsTable(RegistrationApplication $application): string
    {
        $rows = [];

        foreach ($application->fieldReviews as $review) {
            if ($review->status !== RegistrationApplicationFieldReview::STATUS_REJECTED) {
                continue;
            }

            $label = __("registration_review.fields.{$review->field_key}");
            $value = $application->snapshot[$review->field_key] ?? '—';
            $comment = $review->comment ? ' — '.$review->comment : '';
            $rows[] = "<li><strong>{$label}</strong>: {$value}{$comment}</li>";
        }

        if ($rows === []) {
            return '';
        }

        return '<ul>'.implode('', $rows).'</ul>';
    }

    /** @param array<string, string> $extra */
    private function buildReplacements(User $user, array $extra): array
    {
        return array_merge([
            'name' => $user->first_name ?: $user->displayName(),
            'note' => $extra['note'] ?? '',
            'fields_table' => $extra['fields_table'] ?? '',
            'portal_url' => route('application.status'),
            'correction_url' => route('application.edit'),
        ], $extra);
    }
}
