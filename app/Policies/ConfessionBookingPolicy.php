<?php

namespace App\Policies;

use App\Models\ConfessionBooking;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Services\Pastoral\PriestDelegationService;
use App\Tenancy\TenantContext;

class ConfessionBookingPolicy
{
    public function __construct(
        private PriestDelegationService $delegation,
        private CoursePermissionResolver $resolver,
    ) {}

    public function view(User $user, ConfessionBooking $booking): bool
    {
        if ((int) $booking->user_id === (int) $user->user_id) {
            return $this->canChurch($user, 'confession.book') || $this->canChurch($user, 'confession.view');
        }

        $priest = $booking->slot?->priest;
        if ($priest && $this->delegation->canManageConfessionSlots($user, $priest)) {
            return true;
        }

        return $this->canChurch($user, 'confession.manage');
    }

    public function create(User $user): bool
    {
        return $this->canChurch($user, 'confession.book')
            || $this->canChurch($user, 'confession.book_on_behalf');
    }

    public function bookOnBehalf(User $user): bool
    {
        return $this->delegation->canBookOnBehalf($user, 'confession.book_on_behalf');
    }

    public function update(User $user, ConfessionBooking $booking): bool
    {
        if ((int) $booking->user_id === (int) $user->user_id && $this->canChurch($user, 'confession.book')) {
            return true;
        }

        if ($this->bookOnBehalf($user)) {
            return true;
        }

        $priest = $booking->slot?->priest;

        return $priest && $this->delegation->canManageConfessionSlots($user, $priest);
    }

    public function cancel(User $user, ConfessionBooking $booking): bool
    {
        return $this->update($user, $booking);
    }

    private function canChurch(User $user, string $key): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $church = TenantContext::current();

        return $church && $this->resolver->canInChurch($user, $key, $church);
    }
}
