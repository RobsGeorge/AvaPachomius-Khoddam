<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Mail\NotificationMail;
use App\Models\RoleAssignmentEmailTemplate;
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
            if ($type === 'role_assigned') {
                $metadata = $mailNotification->metadata ?? [];
                $templateKey = ($metadata['scope'] ?? 'course') === 'system'
                    ? RoleAssignmentEmailTemplate::KEY_SYSTEM_ROLE_ASSIGNED
                    : RoleAssignmentEmailTemplate::KEY_COURSE_ROLE_ASSIGNED;

                app(RoleAssignmentMailService::class)->send($user, $templateKey, [
                    'role_name' => $metadata['role_name'] ?? $title,
                    'course_title' => $metadata['course_title'] ?? '',
                    'portal_url' => $actionUrl ?? route('dashboard'),
                ]);
            } else {
                Mail::to($user->email)->send(new NotificationMail($user, $mailNotification));
            }
        }

        if ($pref->whatsapp_enabled && $this->whatsapp->isConfigured() && $notification) {
            SendWhatsAppNotificationJob::dispatch($notification->id, $user->user_id);
        }
    }
}
