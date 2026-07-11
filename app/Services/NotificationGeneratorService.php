<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

class NotificationGeneratorService
{
    public function __construct(
        private NotificationDispatchService $dispatch,
        private NotificationPreferenceService $preferences
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
    ): UserNotification {
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
