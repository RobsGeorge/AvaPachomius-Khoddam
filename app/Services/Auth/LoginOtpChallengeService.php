<?php

namespace App\Services\Auth;

use App\Mail\SendOTPEmail;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class LoginOtpChallengeService
{
    public const SESSION_USER_KEY = 'login_otp_user_id';

    public const SESSION_CHANNEL_KEY = 'login_otp_channel';

    public function __construct(
        private WhatsAppNotificationService $whatsapp,
    ) {}

    /**
     * @return array{ok: bool, reason?: string}
     */
    public function issue(User $user, string $channel): array
    {
        $key = 'login-otp-issue-'.$user->user_id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        $code = random_int(100000, 999999);

        OtpCode::updateOrCreate(
            ['user_id' => $user->user_id],
            ['code' => $code, 'expires_at' => now()->addMinutes(10)]
        );

        $sent = $channel === LoginResolutionService::CHANNEL_MOBILE
            ? $this->sendMobileOtp($user, $code)
            : $this->sendEmailOtp($user, $code);

        if (! $sent) {
            return ['ok' => false, 'reason' => 'send_failed'];
        }

        RateLimiter::hit($key, 60);

        AuditLogService::recordEvent('auth.login_otp_issued', [
            'user_id' => $user->user_id,
            'channel' => $channel,
        ]);

        return ['ok' => true];
    }

    public function verify(User $user, string $code): bool
    {
        $otp = OtpCode::query()
            ->where('user_id', $user->user_id)
            ->where('code', $code)
            ->where('expires_at', '>=', now())
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->delete();

        AuditLogService::recordEvent('auth.login_otp_verified', [
            'user_id' => $user->user_id,
        ]);

        return true;
    }

    private function sendEmailOtp(User $user, int $code): bool
    {
        try {
            Mail::to($user->email)->send(new SendOTPEmail($code, $user));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function sendMobileOtp(User $user, int $code): bool
    {
        $result = $this->whatsapp->sendRawText(
            $user,
            __('auth.login_otp_whatsapp_message', ['code' => $code])
        );

        return (bool) ($result['ok'] ?? false);
    }
}
