<?php

namespace App\Policies;

use App\Models\ConfessionSlot;
use App\Models\User;
use App\Services\Pastoral\PriestDelegationService;

class ConfessionSlotPolicy
{
    public function __construct(
        private PriestDelegationService $delegation,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->delegation->hasChurchPermission($user, 'confession.view')
            || $this->delegation->hasChurchPermission($user, 'confession.manage')
            || $this->delegation->hasChurchPermission($user, 'confession.manage_delegated')
            || $this->delegation->hasChurchPermission($user, 'confession.book');
    }

    public function view(User $user, ConfessionSlot $slot): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->delegation->hasChurchPermission($user, 'confession.manage')
            || $this->delegation->hasChurchPermission($user, 'confession.manage_delegated');
    }

    public function update(User $user, ConfessionSlot $slot): bool
    {
        $priest = $slot->priest;
        if (! $priest) {
            return false;
        }

        return $this->delegation->canManageConfessionSlots($user, $priest);
    }

    public function delete(User $user, ConfessionSlot $slot): bool
    {
        return $this->update($user, $slot);
    }
}
