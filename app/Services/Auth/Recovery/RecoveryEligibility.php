<?php

namespace App\Services\Auth\Recovery;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class RecoveryEligibility
{
    public function blocksSelfServe(User $user): bool
    {
        if (Schema::hasColumn('user', 'is_minor') && (bool) $user->is_minor) {
            return true;
        }

        if (Schema::hasColumn('user', 'safeguarding_restricted') && (bool) $user->safeguarding_restricted) {
            return true;
        }

        return false;
    }

    public function hasVerifiedEmail(User $user): bool
    {
        return Schema::hasColumn('user', 'email_verified_at')
            && $user->email_verified_at !== null
            && filled($user->email);
    }

    public function hasVerifiedMobile(User $user): bool
    {
        return Schema::hasColumn('user', 'mobile_verified_at')
            && $user->mobile_verified_at !== null
            && filled($user->mobile_number);
    }

    /**
     * Self-serve rebind requires a second verified identifier (email XOR the channel being replaced).
     */
    public function canSelfServeRebind(User $user, string $purpose): bool
    {
        if ($this->blocksSelfServe($user)) {
            return false;
        }

        if ($purpose === 'rebind_mobile') {
            return $this->hasVerifiedEmail($user);
        }

        if ($purpose === 'rebind_email') {
            return $this->hasVerifiedMobile($user);
        }

        if ($purpose === 'password_reset') {
            return $this->hasVerifiedEmail($user) || $this->hasVerifiedMobile($user);
        }

        return false;
    }

    /**
     * @return list<string> email|mobile
     */
    public function reachableChannels(User $user): array
    {
        $channels = [];
        if ($this->hasVerifiedEmail($user)) {
            $channels[] = 'email';
        }
        if ($this->hasVerifiedMobile($user)) {
            $channels[] = 'mobile';
        }

        return $channels;
    }
}
