<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker as BasePasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use UnexpectedValueException;

class PasswordBroker extends BasePasswordBroker
{
    public function getUser(array $credentials)
    {
        $email = strtolower(trim((string) ($credentials['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user && ! $user instanceof CanResetPasswordContract) {
            throw new UnexpectedValueException('User must implement CanResetPassword interface.');
        }

        return $user;
    }
}
