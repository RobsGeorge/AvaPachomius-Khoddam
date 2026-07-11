<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Mail\NotificationMail;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;

class NotificationDispatchService
{
    public function __construct(
        private NotificationPreferenceService $preferences,
        private WhatsAppNotificationService $whatsapp
    ) {}

    public function dispatch(
        User $user,
        ?UserNotification $notification,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null
    ): void {
        $pref = $this->preferences->ensureMandatoryChannels($user, $type);

        $mailNotification = $notification ?? new UserNotification([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);

        if ($pref->email_enabled && filled($user->email)) {
            Mail::to($user->email)->send(new NotificationMail($user, $mailNotification));
        }

        if ($pref->whatsapp_enabled && $this->whatsapp->isConfigured() && $notification) {
            SendWhatsAppNotificationJob::dispatch($notification->id, $user->user_id);
        }
    }
}
