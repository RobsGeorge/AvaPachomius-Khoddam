<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\User;

/**
 * Support/manual tier — flagged last resort. Still person-side OTP to the asserted channel.
 * No complete method without PossessionProof.
 */
final class SupportRecoveryService
{
    public function __construct(
        private AccountRecoveryService $recovery,
    ) {}

    /**
     * @return array{ok: bool, reason?: string, challenge?: AccountRecoveryChallenge}
     */
    public function vouchAndSendOtp(
        User $supportActor,
        User $subject,
        string $purpose,
        string $assertedValue,
    ): array {
        return $this->recovery->beginVouchedRebind(
            $subject,
            $supportActor,
            AccountRecoveryChallenge::TIER_SUPPORT,
            $purpose,
            $assertedValue,
        );
    }
}
