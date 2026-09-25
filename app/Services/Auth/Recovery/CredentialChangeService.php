<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\PossessionProofRecord;
use App\Models\User;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Completes credential changes. EVERY complete path requires a PossessionProof
 * minted only by RecoveryOtpVerifier — there is no admin-callable overload.
 */
final class CredentialChangeService
{
    public function __construct(
        private AccessLedgerRepository $ledger,
    ) {}

    /**
     * @throws ValidationException|LogicException
     */
    public function completeRebind(PossessionProof $proof): User
    {
        return DB::transaction(function () use ($proof) {
            $record = PossessionProofRecord::query()
                ->lockForUpdate()
                ->find($proof->possessionProofId());

            if (! $record || $record->consumed_at !== null) {
                throw ValidationException::withMessages([
                    'proof' => [__('auth.recovery_proof_invalid')],
                ]);
            }

            if (! hash_equals((string) $record->token_hash, hash('sha256', $proof->plaintextToken()))) {
                throw ValidationException::withMessages([
                    'proof' => [__('auth.recovery_proof_invalid')],
                ]);
            }

            if ($record->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'proof' => [__('auth.recovery_proof_expired')],
                ]);
            }

            if ((int) $record->user_id !== $proof->userId()) {
                throw new LogicException('PossessionProof user mismatch.');
            }

            $challenge = AccountRecoveryChallenge::query()
                ->lockForUpdate()
                ->findOrFail($record->account_recovery_challenge_id);

            $user = User::query()->lockForUpdate()->findOrFail($record->user_id);

            if ($challenge->purpose === AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE) {
                // User::saving clears mobile_verified_at when the number changes unless
                // verified_at is dirty. A same-second now() equals the prior stamp and is
                // NOT dirty — so set number first (hook clears), then stamp in a second save.
                $user->mobile_number = $challenge->asserted_value;
                $user->save();
                $user->mobile_verified_at = now();
                $user->save();
            } elseif ($challenge->purpose === AccountRecoveryChallenge::PURPOSE_REBIND_EMAIL) {
                $user->email = $challenge->asserted_value;
                $user->email_verified_at = now();
                $user->save();
            } else {
                throw ValidationException::withMessages([
                    'purpose' => [__('auth.recovery_purpose_unsupported')],
                ]);
            }

            $record->consumed_at = now();
            $record->save();

            $challenge->outcome = AccountRecoveryChallenge::OUTCOME_COMPLETED;
            $challenge->phase = AccountRecoveryChallenge::PHASE_COMPLETED;
            $challenge->consumed_at = now();
            $challenge->save();

            $this->ledger->append([
                'actor_type' => 'user',
                'actor_id' => (int) $user->user_id,
                'action' => 'recovery',
                'subject_type' => User::class,
                'subject_id' => (int) $user->user_id,
                'context' => [
                    'tier' => $challenge->tier,
                    'purpose' => $challenge->purpose,
                    'outcome' => 'completed',
                    'channel' => $challenge->asserted_channel,
                    'challenge_id' => (int) $challenge->account_recovery_challenge_id,
                    'vouched_by' => $challenge->vouched_by_user_id,
                ],
            ]);

            AuditLogService::recordEvent('auth.recovery_completed', [
                'user_id' => $user->user_id,
                'purpose' => $challenge->purpose,
                'tier' => $challenge->tier,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Password set after possession proof (OTP path). Email-link reset uses Laravel's
     * broker separately but still goes through AccountRecoveryService gates on start.
     */
    public function completePasswordReset(PossessionProof $proof, string $newPassword): User
    {
        return DB::transaction(function () use ($proof, $newPassword) {
            $record = PossessionProofRecord::query()
                ->lockForUpdate()
                ->find($proof->possessionProofId());

            if (! $record || $record->consumed_at !== null) {
                throw ValidationException::withMessages([
                    'proof' => [__('auth.recovery_proof_invalid')],
                ]);
            }

            if (! hash_equals((string) $record->token_hash, hash('sha256', $proof->plaintextToken()))) {
                throw ValidationException::withMessages([
                    'proof' => [__('auth.recovery_proof_invalid')],
                ]);
            }

            if ($record->purpose !== AccountRecoveryChallenge::PURPOSE_PASSWORD_RESET) {
                throw ValidationException::withMessages([
                    'purpose' => [__('auth.recovery_purpose_unsupported')],
                ]);
            }

            $user = User::query()->lockForUpdate()->findOrFail($record->user_id);
            $user->password = Hash::make($newPassword);
            $user->remember_token = Str::random(60);
            $user->save();

            $record->consumed_at = now();
            $record->save();

            $challenge = AccountRecoveryChallenge::query()->find($record->account_recovery_challenge_id);
            if ($challenge) {
                $challenge->outcome = AccountRecoveryChallenge::OUTCOME_COMPLETED;
                $challenge->phase = AccountRecoveryChallenge::PHASE_COMPLETED;
                $challenge->consumed_at = now();
                $challenge->save();
            }

            $this->ledger->append([
                'actor_type' => 'user',
                'actor_id' => (int) $user->user_id,
                'action' => 'recovery',
                'subject_type' => User::class,
                'subject_id' => (int) $user->user_id,
                'context' => [
                    'tier' => $challenge?->tier ?? 'self_serve',
                    'purpose' => 'password_reset',
                    'outcome' => 'completed',
                    'challenge_id' => $challenge ? (int) $challenge->account_recovery_challenge_id : null,
                ],
            ]);

            AuditLogService::recordEvent('auth.password_changed', [
                'user_id' => $user->user_id,
                'source' => 'recovery_otp',
                'tier' => $challenge?->tier ?? 'self_serve',
            ]);

            return $user->fresh();
        });
    }
}
