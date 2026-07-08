<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class ProfilePhotoGateService
{
    public const GRACE_DAYS = 3;

    public function timezone(): string
    {
        return config('attendance.timezone', config('app.timezone'));
    }

    public function appliesTo(User $user): bool
    {
        if ($user->is_superadmin ?? false) {
            return false;
        }

        if (! $user->isStudent()) {
            return false;
        }

        return ! $user->hasProfilePhoto();
    }

    public function ensureGraceStarted(User $user): void
    {
        if (! $this->appliesTo($user) || $user->profile_photo_grace_started_at) {
            return;
        }

        $user->forceFill([
            'profile_photo_grace_started_at' => now($this->timezone()),
        ])->save();
    }

    public function deadlineFor(User $user): ?Carbon
    {
        if (! $this->appliesTo($user) || ! $user->profile_photo_grace_started_at) {
            return null;
        }

        return $user->profile_photo_grace_started_at
            ->copy()
            ->timezone($this->timezone())
            ->addDays(self::GRACE_DAYS);
    }

    public function isWithinGracePeriod(User $user): bool
    {
        $deadline = $this->deadlineFor($user);

        if (! $deadline) {
            return false;
        }

        return now($this->timezone())->lt($deadline);
    }

    public function isHardBlocked(User $user): bool
    {
        if (! $this->appliesTo($user) || ! $user->profile_photo_grace_started_at) {
            return false;
        }

        return ! $this->isWithinGracePeriod($user);
    }

    public function daysRemaining(User $user): ?int
    {
        $deadline = $this->deadlineFor($user);

        if (! $deadline) {
            return null;
        }

        $now = now($this->timezone());

        if ($now->gte($deadline)) {
            return 0;
        }

        return (int) $now->diffInDays($deadline, false) + 1;
    }

    public function shouldShowWarningBanner(User $user): bool
    {
        return $this->appliesTo($user)
            && $user->profile_photo_grace_started_at
            && $this->isWithinGracePeriod($user);
    }
}
