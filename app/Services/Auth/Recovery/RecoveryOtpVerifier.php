<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\PossessionProofRecord;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sole mint site for PossessionProof. Admin vouch never reaches here without
 * the person submitting the OTP sent to the asserted (new) channel.
 */
final class RecoveryOtpVerifier
{
    public function __construct(
        private RecoveryNotifier $notifier,
    ) {}

    /**
     * Verify the current-phase OTP on a challenge.
     *
     * Self-serve proof phase → advances to asserted phase and sends OTP to NEW value.
     * Asserted phase (or admin/support single phase) → mints PossessionProof.
     *
     * @return array{status: 'advanced'|'proof', proof?: PossessionProof, challenge: AccountRecoveryChallenge}
     */
    public function verify(AccountRecoveryChallenge $challenge, string $otp): array
    {
        if ($challenge->consumed_at !== null
            || in_array($challenge->outcome, [
                AccountRecoveryChallenge::OUTCOME_COMPLETED,
                AccountRecoveryChallenge::OUTCOME_REJECTED,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'otp' => [__('auth.recovery_challenge_closed')],
            ]);
        }

        if ($challenge->otp_expires_at === null || $challenge->otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => [__('auth.recovery_otp_expired')],
            ]);
        }

        if ($challenge->otp_hash === null || ! Hash::check($otp, $challenge->otp_hash)) {
            throw ValidationException::withMessages([
                'otp' => [__('auth.recovery_otp_invalid')],
            ]);
        }

        $user = User::query()->findOrFail($challenge->user_id);

        // Self-serve: after proving the second channel, send OTP to the NEW identifier.
        if ($challenge->tier === AccountRecoveryChallenge::TIER_SELF_SERVE
            && $challenge->phase === AccountRecoveryChallenge::PHASE_PROOF
            && in_array($challenge->purpose, [
                AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
                AccountRecoveryChallenge::PURPOSE_REBIND_EMAIL,
            ], true)
        ) {
            $code = random_int(100000, 999999);
            $challenge->phase = AccountRecoveryChallenge::PHASE_ASSERTED;
            $challenge->otp_hash = Hash::make((string) $code);
            $challenge->otp_expires_at = now()->addMinutes(10);
            $challenge->outcome = AccountRecoveryChallenge::OUTCOME_OTP_SENT;
            $challenge->save();

            $sent = $this->notifier->sendOtpToAssertedValue(
                $user,
                (string) $challenge->asserted_channel,
                (string) $challenge->asserted_value,
                $code
            );

            if (! $sent) {
                throw ValidationException::withMessages([
                    'otp' => [__('auth.recovery_otp_send_failed')],
                ]);
            }

            return ['status' => 'advanced', 'challenge' => $challenge->fresh()];
        }

        // Final OTP (asserted channel / admin-assisted / support / password OTP) → mint proof.
        $plaintext = Str::random(64);
        $record = PossessionProofRecord::query()->create([
            'account_recovery_challenge_id' => $challenge->account_recovery_challenge_id,
            'user_id' => $challenge->user_id,
            'token_hash' => hash('sha256', $plaintext),
            'purpose' => $challenge->purpose,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        $challenge->otp_hash = null;
        $challenge->outcome = AccountRecoveryChallenge::OUTCOME_VERIFIED;
        $challenge->save();

        $proof = PossessionProof::mintFromRecord($record, $plaintext);

        return [
            'status' => 'proof',
            'proof' => $proof,
            'challenge' => $challenge->fresh(),
        ];
    }
}
