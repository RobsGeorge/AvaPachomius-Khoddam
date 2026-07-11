<?php

namespace App\Services;

use App\Models\NotificationWhatsappDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
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

        if (! $this->isConfigured()) {
            return $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => 'WhatsApp API not configured',
            ]) ? $delivery->fresh() : $delivery;
        }

        $phone = $this->normalizePhone($user->mobile_number);
        if ($phone === null) {
            return $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => 'Missing mobile number',
            ]) ? $delivery->fresh() : $delivery;
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
                $delivery->update([
                    'status' => NotificationWhatsappDelivery::STATUS_SENT,
                    'provider_message_id' => $response->json('messages.0.id'),
                    'sent_at' => now(),
                ]);
            } else {
                $delivery->update([
                    'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                    'error' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp notification failed', ['error' => $e->getMessage()]);
            $delivery->update([
                'status' => NotificationWhatsappDelivery::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
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
