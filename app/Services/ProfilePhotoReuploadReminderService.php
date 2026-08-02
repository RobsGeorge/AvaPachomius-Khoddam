<?php

namespace App\Services;

use App\Models\PortalSettings;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;

class ProfilePhotoReuploadReminderService
{
    public function __construct(
        private NotificationGeneratorService $notifications,
    ) {}

    public function scan(): int
    {
        $studentRoleIds = Role::studentRoleIds();
        if ($studentRoleIds->isEmpty()) {
            return 0;
        }

        $settings = PortalSettings::current();
        $reminderDays = max(1, min(30, (int) ($settings->profile_photo_reupload_reminder_days ?? 2)));
        $cutoff = now(config('attendance.timezone', config('app.timezone')))->subDays($reminderDays);
        $generated = 0;

        User::query()
            ->where('profile_photo_status', User::PHOTO_STATUS_REJECTED)
            ->whereNotNull('profile_photo_reviewed_at')
            ->where('profile_photo_reviewed_at', '<=', $cutoff)
            ->where(function ($query) {
                $query->whereNull('profile_photo')
                    ->orWhere('profile_photo', '');
            })
            ->whereHas('userCourseRoles', function ($query) use ($studentRoleIds) {
                $query->whereIn('role_id', $studentRoleIds);
            })
            ->eachById(function (User $student) use (&$generated) {
                $reviewedAt = $student->profile_photo_reviewed_at;
                if (! $reviewedAt) {
                    return;
                }

                $dedupeKey = 'profile_photo_reupload_reminder:'
                    .$student->user_id.':'.$reviewedAt->getTimestamp();

                if (UserNotification::query()
                    ->where('user_id', $student->user_id)
                    ->where('dedupe_key', $dedupeKey)
                    ->exists()) {
                    return;
                }

                $this->notifications->createOrUpdate(
                    $student,
                    'profile_photo_reupload_reminder',
                    __('notifications.generated.profile_photo_reupload_reminder_title'),
                    __('notifications.generated.profile_photo_reupload_reminder_body'),
                    route('profile'),
                    User::class,
                    (int) $student->user_id,
                    UserNotification::PRIORITY_HIGH,
                    [
                        'student_user_id' => $student->user_id,
                        'rejection_reviewed_at' => $reviewedAt->toIso8601String(),
                    ],
                    $dedupeKey,
                );

                $generated++;
            }, column: 'user_id');

        return $generated;
    }
}
