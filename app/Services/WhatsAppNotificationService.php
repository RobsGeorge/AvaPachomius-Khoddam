<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\NotificationWhatsappDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function __construct(
        private CommunicationLogService $communicationLogs,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('notifications.whatsapp.api_url'))
            && filled(config('notifications.whatsapp.api_token'))
            && filled(config('notifications.whatsapp.phone_number_id'));
    }

    public function send(UserNotification $notification, User $user): NotificationWhatsappDelivery
    {
        $delivery = NotificationWhatsappDelivery::create([
            'user_notification_id' => $notification->id,
            'user_id' => $user->user_id,
            'status' => NotificationWhatsappDelivery::STATUS_PENDING,
        ]);

        $metadata = is_array($notification->metadata) ? $notification->metadata : [];
        $log = $this->communicationLogs->record([
            'user' => $user,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'subject' => $notification->title,
            'body_preview' => $notification->body,
            'course_id' => isset($metadata['course_id']) ? (int) $metadata['course_id'] : null,
            'service_id' => isset($metadata['service_id']) ? (int) $metadata['service_id'] : null,
            'related_type' => UserNotification::class,
            'related_id' => $notification->id,
            'metadata' => ['whatsapp_delivery_id' => $delivery->id, 'type' => $notification->type],
        ]);

        if (! $this->isConfigured()) {
            $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => 'WhatsApp API not configured',
            ]);
            $this->communicationLogs->markFailed($log, 'WhatsApp API not configured');

            return $delivery->fresh();
        }

        $phone = $this->normalizePhone($user->mobile_number);
        if ($phone === null) {
            $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => 'Missing mobile number',
            ]);
            $this->communicationLogs->markFailed($log, __('communications.missing_mobile'));

            return $delivery->fresh();
        }

        if ($log) {
            $log->update(['recipient_mobile' => $phone]);
        }

        try {
            $url = rtrim((string) config('notifications.whatsapp.api_url'), '/')
                .'/'.config('notifications.whatsapp.phone_number_id').'/messages';

            $response = Http::withToken((string) config('notifications.whatsapp.api_token'))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'body' => $notification->title."\n\n".$notification->body,
                    ],
                ]);

            if ($response->successful()) {
                $providerId = $response->json('messages.0.id');
                $delivery->update([
                    'status' => NotificationWhatsappDelivery::STATUS_SENT,
                    'provider_message_id' => $providerId,
                    'sent_at' => now(),
                ]);
                $this->communicationLogs->markSent($log, $providerId);
            } else {
                $delivery->update([
                    'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                    'error' => $response->body(),
                ]);
                $this->communicationLogs->markFailed($log, $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp notification failed', ['error' => $e->getMessage()]);
            $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
            $this->communicationLogs->markFailed($log, $e->getMessage());
        }

        return $delivery->fresh();
    }

    /**
     * Raw WhatsApp text send outside the UserNotification pipeline — used for
     * mobile-number self-verification codes (Contact Verification CV1 slice).
     *
     * @return array{ok: bool, provider_message_id?: string|null, error?: string}
     */
    public function sendRawText(User $user, string $body): array
    {
        return $this->dispatchRawTextToMobile((string) $user->mobile_number, $body, $user);
    }

    /**
     * Account-lifecycle WhatsApp (e.g. superadmin deletion notice).
     *
     * @return array{ok: bool, provider_message_id?: string|null, error?: string}
     */
    public function sendAccountNotice(User $user, string $subject, string $body): array
    {
        return $this->dispatchRawTextToMobile(
            (string) $user->mobile_number,
            $body,
            $user,
            'account_deletion',
            $subject,
        );
    }

    /**
     * Send WhatsApp text to an arbitrary mobile (e.g. recovery rebind to a NEW number).
     */
    public function sendRawTextToMobile(string $mobileNumber, string $body, ?User $relatedUser = null): bool
    {
        return (bool) ($this->dispatchRawTextToMobile($mobileNumber, $body, $relatedUser)['ok'] ?? false);
    }

    /**
     * @return array{ok: bool, provider_message_id?: string|null, error?: string}
     */
    private function dispatchRawTextToMobile(
        string $mobileNumber,
        string $body,
        ?User $relatedUser = null,
        string $logType = 'mobile_verification',
        ?string $subject = null,
    ): array {
        $log = $this->communicationLogs->record([
            'user' => $relatedUser,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'subject' => $subject ?? __('notifications.mobile_verification_subject'),
            'body_preview' => $body,
            'related_type' => $relatedUser ? User::class : null,
            'related_id' => $relatedUser?->user_id,
            'metadata' => ['type' => $logType],
        ]);

        $phone = $this->normalizePhone($mobileNumber);
        if ($phone === null) {
            $this->communicationLogs->markFailed($log, __('communications.missing_mobile'));

            return ['ok' => false, 'error' => 'missing_mobile'];
        }

        if (! $this->isConfigured()) {
            $this->communicationLogs->markFailed($log, 'WhatsApp API not configured');

            return ['ok' => false, 'error' => 'not_configured'];
        }

        if ($log) {
            $log->update(['recipient_mobile' => $phone]);
        }

        try {
            $url = rtrim((string) config('notifications.whatsapp.api_url'), '/')
                .'/'.config('notifications.whatsapp.phone_number_id').'/messages';

            $response = Http::withToken((string) config('notifications.whatsapp.api_token'))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]);

            if ($response->successful()) {
                $providerId = $response->json('messages.0.id');
                $this->communicationLogs->markSent($log, $providerId);

                return ['ok' => true, 'provider_message_id' => $providerId];
            }

            $this->communicationLogs->markFailed($log, $response->body());

            return ['ok' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp mobile verification send failed', ['error' => $e->getMessage()]);
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

        if (str_starts_with($digits, '20')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '20'.substr($digits, 1);
        }

        return '20'.$digits;
    }
}
