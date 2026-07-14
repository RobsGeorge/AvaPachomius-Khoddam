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
