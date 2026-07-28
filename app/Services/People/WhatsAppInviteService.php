<?php

namespace App\Services\People;

use App\Models\CommunicationLog;
use App\Models\Invitation;
use App\Services\CommunicationLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound WhatsApp invite (first-contact). Allowed before mobile_verified_at —
 * claiming the invite is the verification path. Requires Meta template when
 * WHATSAPP_INVITE_TEMPLATE is set; otherwise records a skipped/failed send.
 */
class WhatsAppInviteService
{
    public function __construct(
        private readonly CommunicationLogService $communicationLogs,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('notifications.whatsapp.api_url'))
            && filled(config('notifications.whatsapp.api_token'))
            && filled(config('notifications.whatsapp.phone_number_id'));
    }

    /**
     * @return array{ok: bool, error?: string, provider_message_id?: string|null}
     */
    public function sendInvite(Invitation $invitation, string $plainToken, string $otp): array
    {
        $mobile = $invitation->mobile_number;
        $phone = $this->normalizePhone($mobile);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'missing_mobile'];
        }

        $claimUrl = url('/invitations/'.$plainToken);
        $body = __('people_onboarding.whatsapp_invite_body', [
            'otp' => $otp,
            'url' => $claimUrl,
        ]);

        $log = $this->communicationLogs->record([
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'subject' => __('people_onboarding.whatsapp_invite_subject'),
            'body_preview' => $body,
            'recipient_mobile' => $phone,
            'service_id' => $invitation->service_id,
            'course_id' => $invitation->course_id,
            'related_type' => Invitation::class,
            'related_id' => $invitation->invitation_id,
            'metadata' => ['type' => 'people.invitation'],
        ]);

        if (! $this->isConfigured()) {
            $this->communicationLogs->markFailed($log, 'WhatsApp API not configured');

            return ['ok' => false, 'error' => 'not_configured'];
        }

        $template = config('notifications.whatsapp.invite_template');

        try {
            $url = rtrim((string) config('notifications.whatsapp.api_url'), '/')
                .'/'.config('notifications.whatsapp.phone_number_id').'/messages';

            $payload = filled($template)
                ? [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => config('notifications.whatsapp.invite_template_lang', 'ar')],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $otp],
                                    ['type' => 'text', 'text' => $claimUrl],
                                ],
                            ],
                        ],
                    ],
                ]
                : [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $body],
                ];

            $response = Http::withToken((string) config('notifications.whatsapp.api_token'))
                ->post($url, $payload);

            if ($response->successful()) {
                $providerId = $response->json('messages.0.id');
                $this->communicationLogs->markSent($log, $providerId);

                return ['ok' => true, 'provider_message_id' => $providerId];
            }

            $this->communicationLogs->markFailed($log, $response->body());

            return ['ok' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp invite failed', ['error' => $e->getMessage()]);
            $this->communicationLogs->markFailed($log, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizePhone(?string $mobile): ?string
    {
        if (! filled($mobile)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            $digits = '2'.$digits;
        }

        return $digits;
    }
}
