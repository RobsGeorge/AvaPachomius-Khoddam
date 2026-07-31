<?php

namespace Tests\Support;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

trait LogsInWithOtp
{
    protected function loginWithOtp(User $user, ?string $route = null, ?array $params = null): TestResponse
    {
        Mail::fake();

        $params ??= [];
        $identifier = $params['identifier'] ?? $user->email;
        unset($params['identifier']);

        $this->post($route ?? route('login'), array_merge([
            'identifier' => $identifier,
        ], $params));

        $otp = OtpCode::query()->where('user_id', $user->user_id)->value('code');

        if (! $otp) {
            $otp = '123456';
            OtpCode::updateOrCreate(
                ['user_id' => $user->user_id],
                ['code' => $otp, 'expires_at' => now()->addMinutes(10)]
            );
        }

        return $this->post(route('login.otp.verify'), array_merge([
            'otp' => (string) $otp,
        ], $params));
    }
}
