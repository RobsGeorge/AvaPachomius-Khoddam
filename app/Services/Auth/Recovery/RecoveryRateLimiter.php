<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account recovery rate limit backed by challenge rows (survives app restart).
 */
final class RecoveryRateLimiter
{
    public function __construct(
        private int $maxPerHour = 5,
        private int $maxPerDay = 15,
    ) {}

    public function tooManyAttempts(User $user): bool
    {
        if (! Schema::hasTable('account_recovery_challenges')) {
            return false;
        }

        $hourCount = AccountRecoveryChallenge::query()
            ->where('user_id', $user->user_id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourCount >= $this->maxPerHour) {
            return true;
        }

        $dayCount = AccountRecoveryChallenge::query()
            ->where('user_id', $user->user_id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $dayCount >= $this->maxPerDay;
    }
}
