<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

class NotificationGeneratorService
{
    public function __construct(
        private NotificationDispatchService $dispatch,
        private NotificationPreferenceService $preferences,
        private CommunicationLogService $communicationLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createOrUpdate(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        string $priority = UserNotification::PRIORITY_NORMAL,
        array $metadata = [],
        ?string $dedupeKey = null,
        bool $dispatch = true,
    ): ?UserNotification {
        // Only notify users in this type's audience. Outside it, the user has no preference
        // row for the type; skip rather than 500 (e.g. course closure strips a staff member's
        // role identity, so a graduation-announced notification no longer applies to them).
        if (! $this->preferences->appliesTo($user, $type)) {
            return null;
        }

        $dedupeKey ??= $this->buildDedupeKey($type, $sourceType, $sourceId, $metadata);

        $pref = $this->preferences->ensureMandatoryChannels($user, $type);
        $notification = null;

        if ($pref->portal_enabled || $this->preferences->isMandatory($type)) {
            $notification = UserNotification::query()->updateOrCreate(
                [
                    'user_id' => $user->user_id,
                    'dedupe_key' => $dedupeKey,
                ],
                [
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $actionUrl,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'priority' => $priority,
                    'metadata' => $metadata,
                    'read_at' => null,
                    'dismissed_at' => null,
                ]
            );

            if ($notification->wasRecentlyCreated) {
                $this->communicationLogs->record([
                    'user' => $user,
                    'channel' => CommunicationLog::CHANNEL_PORTAL,
                    'status' => CommunicationLog::STATUS_SENT,
                    'subject' => $title,
                    'body_preview' => $body,
                    'course_id' => isset($metadata['course_id']) ? (int) $metadata['course_id'] : null,
                    'service_id' => isset($metadata['service_id']) ? (int) $metadata['service_id'] : null,
                    'related_type' => UserNotification::class,
                    'related_id' => $notification->id,
                    'sent_at' => now(),
                    'metadata' => ['type' => $type, 'source_type' => $sourceType, 'source_id' => $sourceId],
                ]);
            }
        }

        if ($dispatch) {
            try {
                $this->dispatch->dispatch($user, $notification, $type, $title, $body, $actionUrl);
            } catch (\Throwable $e) {
                Log::warning('Notification dispatch failed', [
                    'user_id' => $user->user_id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification ?? new UserNotification([
            'user_id' => $user->user_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function buildDedupeKey(string $type, ?string $sourceType, ?int $sourceId, array $metadata): string
    {
        if ($sourceType && $sourceId) {
            return "{$type}:{$sourceType}:{$sourceId}";
        }

        if (isset($metadata['dedupe_suffix'])) {
            return "{$type}:{$metadata['dedupe_suffix']}";
        }

        return $type.':'.md5(json_encode($metadata));
    }
}
