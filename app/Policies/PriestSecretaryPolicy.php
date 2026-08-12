<?php

namespace App\Policies;

use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\User;
use App\Services\Pastoral\PriestDelegationService;

class PriestSecretaryPolicy
{
    public function __construct(
        private PriestDelegationService $delegation,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->delegation->hasChurchPermission($user, 'priest.view')
            || $this->delegation->hasChurchPermission($user, 'priest.manage');
    }

    public function view(User $user, PriestSecretary $row): bool
    {
        $priest = $row->priest;
        if (! $priest) {
            return false;
        }

        return $this->delegation->canAssignSecretaries($user, $priest)
            || $this->delegation->isActiveDelegate($user, $priest)
            || $this->delegation->ownsPriestRecord($user, $priest);
    }

    public function create(User $user, ?Priest $priest = null): bool
    {
        if (! $priest) {
            return $this->delegation->hasChurchPermission($user, 'priest.manage');
        }

        return $this->delegation->canAssignSecretaries($user, $priest);
    }

    public function update(User $user, PriestSecretary $row): bool
    {
        $priest = $row->priest;

        return $priest && $this->delegation->canAssignSecretaries($user, $priest);
    }

    public function delete(User $user, PriestSecretary $row): bool
    {
        return $this->update($user, $row);
    }
}
