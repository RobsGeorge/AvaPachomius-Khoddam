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
        private CommunicationLogService $communicationLogs,
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

    /**
     * @param  array<string, mixed>  $extra
     */
    public function send(User $user, string $templateKey, array $extra = []): void
    {
        $courseId = isset($extra['course_id']) ? (int) $extra['course_id'] : null;
        $serviceId = isset($extra['service_id']) ? (int) $extra['service_id'] : null;
        unset($extra['course_id'], $extra['service_id']);

        if (! filled($user->email)) {
            $this->communicationLogs->markSkipped(
                $this->communicationLogs->record([
                    'user' => $user,
                    'channel' => \App\Models\CommunicationLog::CHANNEL_EMAIL,
                    'subject' => $templateKey,
                    'course_id' => $courseId,
                    'service_id' => $serviceId,
                    'metadata' => ['template' => $templateKey, 'family' => 'role_assignment'],
                ]),
                __('communications.missing_email')
            );

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

        $log = $this->communicationLogs->record([
            'user' => $user,
            'channel' => \App\Models\CommunicationLog::CHANNEL_EMAIL,
            'subject' => $template->subject,
            'body_preview' => $template->body_html,
            'course_id' => $courseId,
            'service_id' => $serviceId,
            'metadata' => ['template' => $templateKey, 'family' => 'role_assignment'],
        ]);

        try {
            Mail::to($user->email)->send(
                (new RoleAssignmentMail(
                    $user,
                    $template,
                    $this->buildReplacements($user, $extra)
                ))->with(['trackingToken' => $log?->tracking_token])
            );
            $this->communicationLogs->markSent($log);
        } catch (\Throwable $e) {
            $this->communicationLogs->markFailed($log, $e->getMessage());
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
