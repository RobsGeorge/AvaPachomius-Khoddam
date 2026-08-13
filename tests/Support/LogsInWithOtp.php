<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Helper for feature tests that need a real web login (email + password).
 * Method name kept for call-site compatibility after the OTP login rollback.
 */
trait LogsInWithOtp
{
    protected function loginWithOtp(User $user, ?string $route = null, ?array $params = null): TestResponse
    {
        $params ??= [];
        $email = $params['email'] ?? $params['identifier'] ?? $user->email;
        $password = $params['password'] ?? 'password';
        unset($params['email'], $params['identifier'], $params['password']);

        return $this->post($route ?? route('login'), array_merge([
            'email' => $email,
            'password' => $password,
        ], $params));
    }
}
