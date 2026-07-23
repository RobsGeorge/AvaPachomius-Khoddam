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

        $this->healLegacyZeroDates($user);
        $this->refreshGracePeriodIfNeeded($user);

        if (! $this->appliesTo($user) || $this->safeDate($user, 'profile_photo_grace_started_at')) {
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

        $enabledAt = $this->safeSettingsDate('profile_photo_gate_enabled_at');
        $started = $this->safeDate($user, 'profile_photo_grace_started_at');

        if (! $enabledAt || ! $started) {
            return;
        }

        if ($started->lt($enabledAt)) {
            $user->forceFill([
                'profile_photo_grace_started_at' => null,
                'profile_photo_deadline_at' => null,
            ])->save();
        }
    }

    public function deadlineFor(User $user): ?Carbon
    {
        $started = $this->safeDate($user, 'profile_photo_grace_started_at');
        if (! $this->appliesTo($user) || ! $started) {
            return null;
        }

        $override = $this->safeDate($user, 'profile_photo_deadline_at');
        if ($override) {
            return $override->copy()->timezone($this->timezone());
        }

        return $started
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

        if (! $this->appliesTo($user) || ! $this->safeDate($user, 'profile_photo_grace_started_at')) {
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
            && $this->safeDate($user, 'profile_photo_grace_started_at')
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
        return $this->complianceStatus($user);
    }

    /**
     * Admin-report status for an enrolled student. Does not call isStudent()
     * (permission-resolver heavy / false-negative when learner keys are unsynced).
     */
    public function complianceStatus(User $user): string
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

        $started = $this->safeDate($user, 'profile_photo_grace_started_at');
        if (! $started) {
            return 'not_started';
        }

        if (! $this->isEnabled()) {
            return 'in_grace';
        }

        $deadline = $this->complianceDeadline($user, $started);
        if ($deadline && now($this->timezone())->gte($deadline)) {
            return 'overdue';
        }

        return 'in_grace';
    }

    /**
     * Deadline for admin compliance UI (enrolled students; no isStudent() gate).
     */
    public function complianceDeadline(User $user, ?Carbon $started = null): ?Carbon
    {
        if ($user->isProfilePhotoApproved() || $user->isProfilePhotoPending()) {
            return null;
        }

        $started ??= $this->safeDate($user, 'profile_photo_grace_started_at');
        if (! $started) {
            return null;
        }

        $override = $this->safeDate($user, 'profile_photo_deadline_at');
        if ($override) {
            return $override->timezone($this->timezone());
        }

        return $started->copy()->timezone($this->timezone())->addDays($this->graceDays());
    }

    public function safeDate(User $user, string $attribute): ?Carbon
    {
        $raw = $user->getAttributes()[$attribute] ?? null;

        if ($raw === null || $raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        if (is_string($raw) && str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        try {
            $value = $user->getAttribute($attribute);
        } catch (\Throwable) {
            return null;
        }

        if (! $value instanceof Carbon) {
            try {
                $value = Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($value->year < 1) {
            return null;
        }

        return $value;
    }

    public function safeSettingsDate(string $attribute): ?Carbon
    {
        $settings = $this->settings();
        $raw = $settings->getAttributes()[$attribute] ?? null;

        if ($raw === null || $raw === '' || (is_string($raw) && str_starts_with($raw, '0000-00-00'))) {
            return null;
        }

        try {
            $value = $settings->getAttribute($attribute);
        } catch (\Throwable) {
            return null;
        }

        if (! $value instanceof Carbon) {
            try {
                $value = Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($value->year < 1) {
            return null;
        }

        return $value;
    }

    /**
     * Null out MySQL zero-dates so datetime casts never throw on later reads.
     */
    private function healLegacyZeroDates(User $user): void
    {
        $fix = [];
        foreach (['profile_photo_grace_started_at', 'profile_photo_deadline_at'] as $attribute) {
            $raw = $user->getAttributes()[$attribute] ?? null;
            if (is_string($raw) && str_starts_with($raw, '0000-00-00')) {
                $fix[$attribute] = null;
            }
        }

        if ($fix === []) {
            return;
        }

        $user->forceFill($fix)->save();
    }
}
