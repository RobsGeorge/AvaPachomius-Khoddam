<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class DatabaseTokenRepository extends BaseDatabaseTokenRepository
{
    public function exists(CanResetPasswordContract $user, $token)
    {
        $token = PasswordResetToken::normalize((string) $token);
        $record = $this->findRecord($user);

        return $record
            && ! $this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = $this->findRecord($user);

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    public function createNewToken()
    {
        return PasswordResetToken::generate();
    }

    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->getTable()
            ->whereRaw('lower(email) = ?', [strtolower($user->getEmailForPasswordReset())])
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function findRecord(CanResetPasswordContract $user): array
    {
        $record = $this->getTable()
            ->whereRaw('lower(email) = ?', [strtolower($user->getEmailForPasswordReset())])
            ->first();

        return $record ? (array) $record : [];
    }
}
