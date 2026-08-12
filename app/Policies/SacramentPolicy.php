<?php

namespace App\Policies;

use App\Models\Sacrament;
use App\Models\User;

class SacramentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sacraments.view')
            || $this->allows($user, 'sacraments.record');
    }

    public function view(User $user, Sacrament $sacrament): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sacraments.record');
    }

    public function correct(User $user, Sacrament $sacrament): bool
    {
        return $this->allows($user, 'sacraments.record');
    }

    private function allows(User $user, string $permission): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        return $user->canInSystem($permission);
    }
}
