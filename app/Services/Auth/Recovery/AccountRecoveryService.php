<?php

namespace App\Services\Auth\Recovery;

use App\Models\AccountRecoveryChallenge;
use App\Models\User;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Services\AuditLogService;
use App\Services\Auth\LoginResolutionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class AccountRecoveryService
{
    public function __construct(
        private RecoveryEligibility $eligibility,
        private RecoveryRateLimiter $rateLimiter,
        private RecoveryNotifier $notifier,
        private AccessLedgerRepository $ledger,
        private LoginResolutionService $loginResolution,
    ) {}

    /**
     * Self-serve identifier rebind: prove remaining verified channel, then OTP to NEW value.
     *
     * @return array{ok: bool, reason?: string, challenge?: AccountRecoveryChallenge}
     */
    public function beginSelfServeRebind(
        User $user,
        string $purpose,
        string $assertedValue,
    ): array {
        $tier = AccountRecoveryChallenge::TIER_SELF_SERVE;

        if ($this->eligibility->blocksSelfServe($user)) {
            $this->recordRejected($user, $tier, $purpose, 'self_serve_blocked');
            $this->notifier->notifyRecoveryAttempt($user, 'rejected', $tier);

            return ['ok' => false, 'reason' => 'self_serve_blocked'];
        }

        if (! $this->eligibility->canSelfServeRebind($user, $purpose)) {
            $this->recordRejected($user, $tier, $purpose, 'missing_second_identifier');
            $this->notifier->notifyRecoveryAttempt($user, 'rejected', $tier);

            return ['ok' => false, 'reason' => 'missing_second_identifier'];
        }

        if ($this->rateLimiter->tooManyAttempts($user)) {
            $this->recordRejected($user, $tier, $purpose, 'rate_limited', AccountRecoveryChallenge::OUTCOME_RATE_LIMITED);
            $this->notifier->notifyRecoveryAttempt($user, 'rate_limited', $tier);

            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        if ($purpose === AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE) {
            $proofChannel = 'email';
            $assertedChannel = 'mobile';
        } elseif ($purpose === AccountRecoveryChallenge::PURPOSE_REBIND_EMAIL) {
            $proofChannel = 'mobile';
            $assertedChannel = 'email';
        } else {
            return ['ok' => false, 'reason' => 'invalid_purpose'];
        }

        $assertedValue = $this->normalizeAssertedValue($assertedChannel, $assertedValue);
        $this->assertValueAvailable($assertedChannel, $assertedValue, $user);

        $code = random_int(100000, 999999);
        $challenge = AccountRecoveryChallenge::query()->create([
            'user_id' => $user->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
            'phase' => AccountRecoveryChallenge::PHASE_PROOF,
            'proof_channel' => $proofChannel,
            'asserted_channel' => $assertedChannel,
            'asserted_value' => $assertedValue,
            'otp_hash' => Hash::make((string) $code),
            'otp_expires_at' => now()->addMinutes(10),
            'outcome' => AccountRecoveryChallenge::OUTCOME_OTP_SENT,
            'created_at' => now(),
        ]);

        $sent = $this->notifier->sendOtpToChannel($user, $proofChannel, $code);
        if (! $sent) {
            $challenge->outcome = AccountRecoveryChallenge::OUTCOME_REJECTED;
            $challenge->save();
            $this->notifier->notifyRecoveryAttempt($user, 'rejected', $tier);

            return ['ok' => false, 'reason' => 'send_failed', 'challenge' => $challenge];
        }

        $this->ledger->append([
            'actor_type' => 'user',
            'actor_id' => (int) $user->user_id,
            'action' => 'recovery',
            'subject_type' => User::class,
            'subject_id' => (int) $user->user_id,
            'context' => [
                'tier' => $tier,
                'purpose' => $purpose,
                'outcome' => 'started',
                'channel' => $proofChannel,
                'challenge_id' => (int) $challenge->account_recovery_challenge_id,
            ],
        ]);

        $this->notifier->notifyRecoveryAttempt($user, 'started', $tier);

        AuditLogService::recordEvent('auth.recovery_started', [
            'user_id' => $user->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
        ]);

        return ['ok' => true, 'challenge' => $challenge];
    }

    /**
     * Classic email-link password reset, gated by the recovery ladder.
     *
     * @return array{ok: bool, reason?: string, status?: string}
     */
    public function beginPasswordResetLink(string $email): array
    {
        $tier = AccountRecoveryChallenge::TIER_SELF_SERVE;
        $purpose = AccountRecoveryChallenge::PURPOSE_PASSWORD_RESET;
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        // Opaque response for unknown emails (no user to rate-limit/notify).
        if (! $user) {
            return ['ok' => true, 'status' => Password::RESET_LINK_SENT];
        }

        if ($this->eligibility->blocksSelfServe($user)) {
            $this->recordRejected($user, $tier, $purpose, 'self_serve_blocked');
            $this->notifier->notifyRecoveryAttempt($user, 'rejected', $tier);

            return ['ok' => false, 'reason' => 'self_serve_blocked'];
        }

        if (! $this->eligibility->hasVerifiedEmail($user) && $user->email_verified_at === null) {
            // Treat existing accounts without stamp as email-reachable for legacy reset,
            // but still require email present. Prefer verified when column exists.
        }

        if ($this->rateLimiter->tooManyAttempts($user)) {
            $this->recordRejected($user, $tier, $purpose, 'rate_limited', AccountRecoveryChallenge::OUTCOME_RATE_LIMITED);
            $this->notifier->notifyRecoveryAttempt($user, 'rate_limited', $tier);

            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        $challenge = AccountRecoveryChallenge::query()->create([
            'user_id' => $user->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
            'phase' => AccountRecoveryChallenge::PHASE_PROOF,
            'proof_channel' => 'email',
            'asserted_channel' => null,
            'asserted_value' => null,
            'otp_hash' => null,
            'otp_expires_at' => null,
            'outcome' => AccountRecoveryChallenge::OUTCOME_OTP_SENT,
            'created_at' => now(),
        ]);

        $this->ledger->append([
            'actor_type' => 'user',
            'actor_id' => (int) $user->user_id,
            'action' => 'recovery',
            'subject_type' => User::class,
            'subject_id' => (int) $user->user_id,
            'context' => [
                'tier' => $tier,
                'purpose' => $purpose,
                'outcome' => 'started',
                'channel' => 'email',
                'challenge_id' => (int) $challenge->account_recovery_challenge_id,
            ],
        ]);

        $this->notifier->notifyRecoveryAttempt($user, 'started', $tier);

        $status = Password::sendResetLink(['email' => $email]);

        return ['ok' => true, 'status' => $status, 'challenge' => $challenge];
    }

    /**
     * Shared start for admin-assisted or support vouch → OTP to NEW asserted value only.
     *
     * @return array{ok: bool, reason?: string, challenge?: AccountRecoveryChallenge}
     */
    public function beginVouchedRebind(
        User $subject,
        User $actor,
        string $tier,
        string $purpose,
        string $assertedValue,
    ): array {
        if (! in_array($tier, [
            AccountRecoveryChallenge::TIER_ADMIN_ASSISTED,
            AccountRecoveryChallenge::TIER_SUPPORT,
        ], true)) {
            return ['ok' => false, 'reason' => 'invalid_tier'];
        }

        if ($this->rateLimiter->tooManyAttempts($subject)) {
            $this->recordRejected($subject, $tier, $purpose, 'rate_limited', AccountRecoveryChallenge::OUTCOME_RATE_LIMITED);
            $this->notifier->notifyRecoveryAttempt($subject, 'rate_limited', $tier);

            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        if ($purpose === AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE) {
            $assertedChannel = 'mobile';
        } elseif ($purpose === AccountRecoveryChallenge::PURPOSE_REBIND_EMAIL) {
            $assertedChannel = 'email';
        } else {
            return ['ok' => false, 'reason' => 'invalid_purpose'];
        }

        $assertedValue = $this->normalizeAssertedValue($assertedChannel, $assertedValue);
        $this->assertValueAvailable($assertedChannel, $assertedValue, $subject);

        $code = random_int(100000, 999999);
        $challenge = AccountRecoveryChallenge::query()->create([
            'user_id' => $subject->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
            'phase' => AccountRecoveryChallenge::PHASE_ASSERTED,
            'proof_channel' => null,
            'asserted_channel' => $assertedChannel,
            'asserted_value' => $assertedValue,
            'vouched_by_user_id' => $actor->user_id,
            'otp_hash' => Hash::make((string) $code),
            'otp_expires_at' => now()->addMinutes(10),
            'outcome' => AccountRecoveryChallenge::OUTCOME_OTP_SENT,
            'created_at' => now(),
        ]);

        $sent = $this->notifier->sendOtpToAssertedValue($subject, $assertedChannel, $assertedValue, $code);
        if (! $sent) {
            $challenge->outcome = AccountRecoveryChallenge::OUTCOME_REJECTED;
            $challenge->save();
            $this->notifier->notifyRecoveryAttempt($subject, 'rejected', $tier);

            return ['ok' => false, 'reason' => 'send_failed', 'challenge' => $challenge];
        }

        $this->ledger->append([
            'actor_type' => 'staff',
            'actor_id' => (int) $actor->user_id,
            'action' => 'recovery',
            'subject_type' => User::class,
            'subject_id' => (int) $subject->user_id,
            'context' => [
                'tier' => $tier,
                'purpose' => $purpose,
                'outcome' => 'started',
                'channel' => $assertedChannel,
                'vouched_by' => (int) $actor->user_id,
                'challenge_id' => (int) $challenge->account_recovery_challenge_id,
            ],
        ]);

        $this->notifier->notifyRecoveryAttempt($subject, 'started', $tier);

        AuditLogService::recordEvent('auth.recovery_vouched', [
            'user_id' => $subject->user_id,
            'actor_id' => $actor->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
        ]);

        return ['ok' => true, 'challenge' => $challenge];
    }

    private function recordRejected(
        User $user,
        string $tier,
        string $purpose,
        string $reasonCode,
        string $outcome = AccountRecoveryChallenge::OUTCOME_REJECTED,
    ): void {
        $challenge = AccountRecoveryChallenge::query()->create([
            'user_id' => $user->user_id,
            'tier' => $tier,
            'purpose' => $purpose,
            'phase' => AccountRecoveryChallenge::PHASE_PROOF,
            'outcome' => $outcome,
            'created_at' => now(),
        ]);

        $this->ledger->append([
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'recovery',
            'subject_type' => User::class,
            'subject_id' => (int) $user->user_id,
            'context' => [
                'tier' => $tier,
                'purpose' => $purpose,
                'outcome' => $outcome,
                'reason_code' => $reasonCode,
                'challenge_id' => (int) $challenge->account_recovery_challenge_id,
            ],
        ]);
    }

    private function normalizeAssertedValue(string $channel, string $value): string
    {
        $value = trim($value);
        if ($channel === 'email') {
            return strtolower($value);
        }

        $variants = $this->loginResolution->mobileLookupValues($value);

        return $variants[1] ?? $variants[0] ?? $value; // prefer 0-prefixed local
    }

    private function assertValueAvailable(string $channel, string $value, User $except): void
    {
        if ($channel === 'email') {
            $taken = User::query()
                ->where('email', $value)
                ->where('user_id', '!=', $except->user_id)
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages([
                    'asserted_value' => [__('auth.recovery_identifier_taken')],
                ]);
            }

            return;
        }

        $taken = User::query()
            ->whereIn('mobile_number', $this->loginResolution->mobileLookupValues($value))
            ->where('user_id', '!=', $except->user_id)
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'asserted_value' => [__('auth.recovery_identifier_taken')],
            ]);
        }
    }
}
