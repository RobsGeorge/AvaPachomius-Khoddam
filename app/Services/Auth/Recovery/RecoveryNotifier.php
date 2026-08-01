<?php

namespace App\Services\Auth\Recovery;

use App\Mail\SendOTPEmail;
use App\Models\User;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Mail;
final class RecoveryNotifier
{
    public function __construct(
        private RecoveryEligibility $eligibility,
        private AccessLedgerRepository $ledger,
        private WhatsAppNotificationService $whatsapp,
    ) {}

    /**
     * Alert every still-reachable verified channel that recovery was requested.
     * Called for starts that succeed OR are rejected/rate-limited.
     */
    public function notifyRecoveryAttempt(User $user, string $outcome, string $tier): void
    {
        $body = __('auth.recovery_alert_body', [
            'outcome' => __('auth.recovery_outcome_'.$outcome),
        ]);

        foreach ($this->eligibility->reachableChannels($user) as $channel) {
            if ($channel === 'email') {
                try {
                    Mail::raw($body, function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject(__('auth.recovery_alert_subject'));
                    });
                } catch (\Throwable) {
                    // Best-effort alert; ledger already records the attempt.
                }
            }

            if ($channel === 'mobile') {
                $this->whatsapp->sendRawText($user, $body);
            }
        }

        $this->ledger->append([
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'recovery',
            'subject_type' => User::class,
            'subject_id' => (int) $user->user_id,
            'church_id' => null,
            'organization_id' => null,
            'context' => [
                'tier' => $tier,
                'outcome' => 'alert_sent',
                'reason_code' => $outcome,
            ],
        ]);
    }

    public function sendOtpToChannel(User $user, string $channel, int $code): bool
    {
        if ($channel === 'email') {
            try {
                Mail::to($user->email)->send(new SendOTPEmail($code, $user));

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        if ($channel === 'mobile') {
            $result = $this->whatsapp->sendRawText(
                $user,
                __('auth.recovery_otp_whatsapp', [
                    'code' => $code,
                    'challenge' => '',
                ])
            );

            return (bool) ($result['ok'] ?? false);
        }

        return false;
    }

    /**
     * Send OTP to a newly asserted identifier (not yet bound on the user).
     */
    public function sendOtpToAssertedValue(User $user, string $channel, string $value, int $code): bool
    {
        if ($channel === 'email') {
            try {
                Mail::to($value)->send(new SendOTPEmail($code, $user));

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        if ($channel === 'mobile') {
            return $this->whatsapp->sendRawTextToMobile(
                $value,
                __('auth.recovery_otp_whatsapp', ['code' => $code]),
                $user
            );
        }

        return false;
    }
}
