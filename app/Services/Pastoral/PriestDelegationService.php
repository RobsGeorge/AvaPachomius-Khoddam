<?php

namespace App\Services\Pastoral;

use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;

/**
 * Permission-key + delegation checks for priest calendars (PAC1).
 * Never compares role names — only permission keys and priest_secretary rows.
 */
class PriestDelegationService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
    ) {}

    public function isActiveDelegate(User $user, Priest $priest): bool
    {
        if (! Schema::hasTable('priest_secretary')) {
            return false;
        }

        return PriestSecretary::query()
            ->where('priest_id', $priest->priest_id)
            ->where('user_id', $user->user_id)
            ->where('status', PriestSecretary::STATUS_ACTIVE)
            ->exists();
    }

    public function ownsPriestRecord(User $user, Priest $priest): bool
    {
        return (int) $priest->user_id === (int) $user->user_id
            && $priest->status === Priest::STATUS_ACTIVE;
    }

    public function canManagePriestSlots(User $user, Priest $priest, string $ownKey, string $delegatedKey): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->ownsPriestRecord($user, $priest) && $this->canChurch($user, $ownKey)) {
            return true;
        }

        return $this->canChurch($user, $delegatedKey) && $this->isActiveDelegate($user, $priest);
    }

    public function canManageConfessionSlots(User $user, Priest $priest): bool
    {
        return $this->canManagePriestSlots(
            $user,
            $priest,
            'confession.manage',
            'confession.manage_delegated',
        );
    }

    public function canManageAppointmentSlots(User $user, Priest $priest): bool
    {
        return $this->canManagePriestSlots(
            $user,
            $priest,
            'appointment.manage',
            'appointment.manage_delegated',
        );
    }

    public function canBookOnBehalf(User $user, string $bookOnBehalfKey): bool
    {
        return $this->hasChurchPermission($user, $bookOnBehalfKey);
    }

    public function hasChurchPermission(User $user, string $permission): bool
    {
        return $this->isSuperadmin($user) || $this->canChurch($user, $permission);
    }

    public function canAssignSecretaries(User $user, Priest $priest): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        if ($this->canChurch($user, 'priest.manage')) {
            return true;
        }

        return $this->ownsPriestRecord($user, $priest) && $this->canChurch($user, 'priest.view');
    }

    private function canChurch(User $user, string $permission): bool
    {
        $church = TenantContext::current();
        if (! $church) {
            return false;
        }

        return $this->resolver->canInChurch($user, $permission, $church);
    }

    private function isSuperadmin(User $user): bool
    {
        return (bool) ($user->is_superadmin ?? false);
    }
}
