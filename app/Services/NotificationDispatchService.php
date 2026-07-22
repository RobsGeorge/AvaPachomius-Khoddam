<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Mail\NotificationMail;
use App\Models\CommunicationLog;
use App\Models\RoleAssignmentEmailTemplate;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;

class NotificationDispatchService
{
    public function __construct(
        private NotificationPreferenceService $preferences,
        private WhatsAppNotificationService $whatsapp,
        private CommunicationLogService $communicationLogs,
    ) {}

    public function dispatch(
        User $user,
        ?UserNotification $notification,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null
    ): void {
        // Only dispatch to users in this type's audience (see NotificationGeneratorService).
        if (! $this->preferences->appliesTo($user, $type)) {
            return;
        }

        $pref = $this->preferences->ensureMandatoryChannels($user, $type);

        $mailNotification = $notification ?? new UserNotification([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);

        $metadata = is_array($mailNotification->metadata ?? null) ? $mailNotification->metadata : [];
        $courseId = isset($metadata['course_id']) ? (int) $metadata['course_id'] : null;
        $serviceId = isset($metadata['service_id']) ? (int) $metadata['service_id'] : null;

        if ($pref->email_enabled) {
            if (! filled($user->email)) {
                $this->communicationLogs->markSkipped(
                    $this->communicationLogs->record([
                        'user' => $user,
                        'channel' => CommunicationLog::CHANNEL_EMAIL,
                        'subject' => $title,
                        'body_preview' => $body,
                        'course_id' => $courseId,
                        'service_id' => $serviceId,
                        'related_type' => $notification ? UserNotification::class : null,
                        'related_id' => $notification?->id,
                        'metadata' => ['type' => $type],
                    ]),
                    __('communications.missing_email')
                );
            } elseif ($type === 'role_assigned') {
                $templateKey = ($metadata['scope'] ?? 'course') === 'system'
                    ? RoleAssignmentEmailTemplate::KEY_SYSTEM_ROLE_ASSIGNED
                    : RoleAssignmentEmailTemplate::KEY_COURSE_ROLE_ASSIGNED;

                app(RoleAssignmentMailService::class)->send($user, $templateKey, [
                    'role_name' => $metadata['role_name'] ?? $title,
                    'course_title' => $metadata['course_title'] ?? '',
                    'portal_url' => $actionUrl ?? route('dashboard'),
                    'course_id' => $courseId,
                    'service_id' => $serviceId,
                ]);
            } else {
                $log = $this->communicationLogs->record([
                    'user' => $user,
                    'channel' => CommunicationLog::CHANNEL_EMAIL,
                    'subject' => $title,
                    'body_preview' => $body,
                    'course_id' => $courseId,
                    'service_id' => $serviceId,
                    'related_type' => $notification ? UserNotification::class : null,
                    'related_id' => $notification?->id,
                    'metadata' => ['type' => $type],
                ]);

                try {
                    Mail::to($user->email)->send(
                        (new NotificationMail($user, $mailNotification))
                            ->with(['trackingToken' => $log?->tracking_token])
                    );
                    $this->communicationLogs->markSent($log);
                } catch (\Throwable $e) {
                    $this->communicationLogs->markFailed($log, $e->getMessage());
                }
            }
        }

        if ($pref->whatsapp_enabled && $this->whatsapp->isConfigured() && $notification) {
            SendWhatsAppNotificationJob::dispatch($notification->id, $user->user_id);
        }
    }
}
