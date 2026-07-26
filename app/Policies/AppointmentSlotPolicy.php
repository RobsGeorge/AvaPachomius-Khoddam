<?php

namespace App\Policies;

use App\Models\AppointmentSlot;
use App\Models\User;
use App\Services\Pastoral\PriestDelegationService;

class AppointmentSlotPolicy
{
    public function __construct(
        private PriestDelegationService $delegation,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->delegation->hasChurchPermission($user, 'appointment.view')
            || $this->delegation->hasChurchPermission($user, 'appointment.manage')
            || $this->delegation->hasChurchPermission($user, 'appointment.manage_delegated')
            || $this->delegation->hasChurchPermission($user, 'appointment.book');
    }

    public function view(User $user, AppointmentSlot $slot): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->delegation->hasChurchPermission($user, 'appointment.manage')
            || $this->delegation->hasChurchPermission($user, 'appointment.manage_delegated');
    }

    public function update(User $user, AppointmentSlot $slot): bool
    {
        $priest = $slot->priest;
        if (! $priest) {
            return false;
        }

        return $this->delegation->canManageAppointmentSlots($user, $priest);
    }

    public function delete(User $user, AppointmentSlot $slot): bool
    {
        return $this->update($user, $slot);
    }
}
