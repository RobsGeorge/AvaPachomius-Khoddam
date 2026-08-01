<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\User;

/**
 * Admin may vouch a new identifier and trigger OTP to that NEW value.
 * This class intentionally has NO complete/rebind method — completion requires
 * PossessionProof from RecoveryOtpVerifier only.
 */
final class AdminAssistedRecoveryService
{
    public function __construct(
        private AccountRecoveryService $recovery,
    ) {}

    /**
     * @return array{ok: bool, reason?: string, challenge?: AccountRecoveryChallenge}
     */
    public function vouchAndSendOtp(
        User $admin,
        User $subject,
        string $purpose,
        string $assertedValue,
    ): array {
        return $this->recovery->beginVouchedRebind(
            $subject,
            $admin,
            AccountRecoveryChallenge::TIER_ADMIN_ASSISTED,
            $purpose,
            $assertedValue,
        );
    }
}
