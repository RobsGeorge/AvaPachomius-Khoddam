<?php

namespace App\Services;

use App\Mail\RoleAssignmentMail;
use App\Models\RoleAssignmentEmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RoleAssignmentMailService
{
    public function __construct(
        private EmailLocaleResolver $localeResolver,
    ) {}

    public function ensureDefaults(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (RoleAssignmentEmailTemplate::keys() as $key) {
                RoleAssignmentEmailTemplate::query()->firstOrCreate(
                    ['template_key' => $key, 'locale' => $locale],
                    [
                        'subject' => __("rbac.email_subjects.{$key}", [], $locale),
                        'body_html' => __("rbac.email_bodies.{$key}", [], $locale),
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
            EmailTemplateCatalog::FAMILY_ROLE_ASSIGNMENT,
            $templateKey
        );
        $template = RoleAssignmentEmailTemplate::query()
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->first()
            ?? RoleAssignmentEmailTemplate::query()
                ->where('template_key', $templateKey)
                ->where('locale', 'ar')
                ->first();

        if (! $template) {
            return;
        }

        try {
            Mail::to($user->email)->send(new RoleAssignmentMail(
                $user,
                $template,
                $this->buildReplacements($user, $extra)
            ));
        } catch (\Throwable $e) {
            Log::warning('Role assignment email failed', [
                'user_id' => $user->user_id,
                'template' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, string> $extra */
    private function buildReplacements(User $user, array $extra): array
    {
        return array_merge([
            'name' => $user->first_name ?: $user->displayName(),
            'role_name' => '',
            'course_title' => '',
            'portal_url' => route('dashboard'),
        ], $extra);
    }
}
