<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventReservationException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EventEligibilityService
{
    public function hasException(User $user, Event $event): bool
    {
        return EventReservationException::where('event_id', $event->event_id)
            ->where('user_id', $user->user_id)
            ->exists();
    }

    public function userRoleNames(User $user): Collection
    {
        return $user->roles->pluck('role_name')->unique()->values();
    }

    public function canView(User $user, Event $event): bool
    {
        if ($event->status !== Event::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->hasException($user, $event)) {
            return true;
        }

        return $this->matchesEligibility($user, $event);
    }

    public function canReserve(User $user, Event $event): bool
    {
        if (! $this->canView($user, $event)) {
            return false;
        }

        if ($event->isCancelled()) {
            return false;
        }

        return $this->registrationWindowOpen($event);
    }

    public function registrationWindowOpen(Event $event): bool
    {
        $now = now();

        if ($event->registration_opens_at && $now->lt($event->registration_opens_at)) {
            return false;
        }

        if ($event->registration_closes_at && $now->gt($event->registration_closes_at)) {
            return false;
        }

        return $now->lte($event->ends_at);
    }

    /** @return \Illuminate\Support\Collection<int, Event> */
    public function visibleEvents(User $user)
    {
        return Event::published()->upcoming()->orderBy('starts_at')->get()
            ->filter(fn (Event $event) => $this->canView($user, $event))
            ->values();
    }

    private function matchesEligibility(User $user, Event $event): bool
    {
        $roleNames = $this->userRoleNames($user);

        return match ($event->visibility) {
            'institution' => $this->rolesMatch($event, $roleNames),
            'course_enrolled' => $event->course_id
                && $user->courses()->where('course.course_id', $event->course_id)->exists()
                && $this->rolesMatch($event, $roleNames),
            'role_based' => $this->rolesMatchRequired($event, $roleNames),
            default => false,
        };
    }

    private function rolesMatch(Event $event, Collection $roleNames): bool
    {
        $eligible = collect($event->eligible_roles ?? []);
        if ($eligible->isEmpty()) {
            return true;
        }

        return $roleNames->intersect($eligible)->isNotEmpty();
    }

    private function rolesMatchRequired(Event $event, Collection $roleNames): bool
    {
        $eligible = collect($event->eligible_roles ?? []);

        if ($eligible->isEmpty() || $roleNames->isEmpty()) {
            return false;
        }

        return $roleNames->intersect($eligible)->isNotEmpty();
    }
}
