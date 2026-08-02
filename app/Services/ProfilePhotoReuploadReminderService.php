<?php

namespace App\Services;

use App\Models\PortalSettings;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProfilePhotoReuploadReminderService
{
    public function __construct(
        private NotificationGeneratorService $notifications,
        private ProfilePhotoGateService $gate,
    ) {}

    /**
     * Remind rejected students whose review is older than the configured threshold
     * and who have not re-uploaded (status still rejected).
     *
     * @return int Number of newly created reminder notifications
     */
    public function sendReminders(): int
    {
        $days = $this->reminderDays();
        $cutoff = now($this->gate->timezone())->subDays($days);

        $students = User::query()
            ->where('profile_photo_status', User::PHOTO_STATUS_REJECTED)
            ->whereNotNull('profile_photo_reviewed_at')
            ->where('profile_photo_reviewed_at', '<=', $cutoff)
            ->get();

        $sent = 0;

        foreach ($students as $student) {
            if (! $student->isStudent()) {
                continue;
            }

            // Re-upload moves status to pending/approved — skip those (query already filters).
            if (! $student->isProfilePhotoRejected()) {
                continue;
            }

            try {
                if ($this->remind($student)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('Profile photo re-upload reminder failed', [
                    'user_id' => $student->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function reminderDays(): int
    {
        $settings = PortalSettings::current();
        $days = (int) ($settings->profile_photo_reupload_reminder_days ?? 2);

        return max(1, min(30, $days > 0 ? $days : 2));
    }

    private function remind(User $student): bool
    {
        $reviewedAt = $student->profile_photo_reviewed_at;
        if (! $reviewedAt instanceof Carbon) {
            return false;
        }

        $dedupeSuffix = (string) $reviewedAt->getTimestamp();
        $dedupeKey = "profile_photo_reupload_reminder:{$student->user_id}:{$dedupeSuffix}";

        $alreadySent = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('dedupe_key', $dedupeKey)
            ->exists();

        if ($alreadySent) {
            return false;
        }

        $this->notifications->createOrUpdate(
            $student,
            'profile_photo_reupload_reminder',
            __('profile_photos.reupload_reminder_title'),
            __('profile_photos.reupload_reminder_body'),
            route('profile'),
            User::class,
            (int) $student->user_id,
            UserNotification::PRIORITY_NORMAL,
            [
                'reviewed_at' => $reviewedAt->toIso8601String(),
                'dedupe_suffix' => "{$student->user_id}:{$dedupeSuffix}",
            ],
            $dedupeKey,
        );

        return true;
    }
}
