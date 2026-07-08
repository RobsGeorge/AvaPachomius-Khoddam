<?php

namespace App\Services;

use App\Models\PortalSettings;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProfilePhotoGateService
{
    public function timezone(): string
    {
        return config('attendance.timezone', config('app.timezone'));
    }

    public function settings(): PortalSettings
    {
        return PortalSettings::current();
    }

    public function graceDays(): int
    {
        return max(1, (int) $this->settings()->profile_photo_grace_days);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings()->profile_photo_gate_enabled;
    }

    public function appliesTo(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($user->is_superadmin ?? false) {
            return false;
        }

        if (! $user->isStudent()) {
            return false;
        }

        if ($user->isProfilePhotoApproved()) {
            return false;
        }

        return ! $user->hasProfilePhoto() || $user->isProfilePhotoRejected();
    }

    public function ensureGraceStarted(User $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->refreshGracePeriodIfNeeded($user);

        if (! $this->appliesTo($user) || $user->profile_photo_grace_started_at) {
            return;
        }

        $user->forceFill([
            'profile_photo_grace_started_at' => now($this->timezone()),
        ])->save();
    }

    private function refreshGracePeriodIfNeeded(User $user): void
    {
        if (! $user->isStudent() || $user->isProfilePhotoApproved()) {
            return;
        }

        $enabledAt = $this->settings()->profile_photo_gate_enabled_at;

        if (! $enabledAt || ! $user->profile_photo_grace_started_at) {
            return;
        }

        if ($user->profile_photo_grace_started_at->lt($enabledAt)) {
            $user->forceFill([
                'profile_photo_grace_started_at' => null,
                'profile_photo_deadline_at' => null,
            ])->save();
        }
    }

    public function deadlineFor(User $user): ?Carbon
    {
        if (! $this->appliesTo($user) || ! $user->profile_photo_grace_started_at) {
            return null;
        }

        if ($user->profile_photo_deadline_at) {
            return $user->profile_photo_deadline_at->copy()->timezone($this->timezone());
        }

        return $user->profile_photo_grace_started_at
            ->copy()
            ->timezone($this->timezone())
            ->addDays($this->graceDays());
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
        if ($user->hasProfilePhoto() && $user->isProfilePhotoPending()) {
            return false;
        }

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

        return (int) $now->copy()->startOfDay()->diffInDays($deadline->copy()->startOfDay()) + 1;
    }

    public function shouldShowWarningBanner(User $user): bool
    {
        return $this->appliesTo($user)
            && $user->profile_photo_grace_started_at
            && $this->isWithinGracePeriod($user);
    }

    public function shouldShowPendingBanner(User $user): bool
    {
        return $this->isEnabled()
            && $user->isStudent()
            && $user->hasProfilePhoto()
            && $user->isProfilePhotoPending();
    }

    public function shouldShowRejectedBanner(User $user): bool
    {
        return $this->appliesTo($user) && $user->isProfilePhotoRejected();
    }

    public function reportStatus(User $user): string
    {
        if ($user->isProfilePhotoApproved()) {
            return 'approved';
        }

        if ($user->isProfilePhotoPending()) {
            return 'pending_review';
        }

        if ($user->isProfilePhotoRejected()) {
            return 'rejected';
        }

        if (! $user->profile_photo_grace_started_at) {
            return 'not_started';
        }

        if ($this->isHardBlocked($user)) {
            return 'overdue';
        }

        if ($this->shouldShowWarningBanner($user)) {
            return 'in_grace';
        }

        return 'unknown';
    }
}
