<?php

namespace App\Services;

use App\Mail\EventCancelledMail;
use App\Mail\EventDemotedToWaitlistMail;
use App\Mail\EventReservationConfirmedMail;
use App\Mail\EventReservationWaitlistMail;
use App\Mail\EventWaitlistPromotedMail;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EventReservationService
{
    public function __construct(
        private EventEligibilityService $eligibility,
    ) {}

    /**
     * @return array{reservation: EventReservation, status: string}
     */
    public function reserve(Event $event, User $user): array
    {
        if (! $this->eligibility->canReserve($user, $event)) {
            EventAuditService::log('reservation.reserve', 'denied', [
                'event_id' => $event->event_id,
                'reason' => 'ineligible_or_closed',
            ], $user);

            throw new \InvalidArgumentException(__('events.not_eligible'));
        }

        $active = EventReservation::where('event_id', $event->event_id)
            ->where('user_id', $user->user_id)
            ->whereIn('status', [EventReservation::STATUS_CONFIRMED, EventReservation::STATUS_WAITLIST])
            ->first();

        if ($active) {
            EventAuditService::log('reservation.reserve', 'denied', [
                'event_id' => $event->event_id,
                'reason' => 'already_reserved',
                'reservation_id' => $active->reservation_id,
                'reservation_status' => $active->status,
            ], $user);

            throw new \InvalidArgumentException(__('events.already_reserved'));
        }

        return DB::transaction(function () use ($event, $user) {
            Event::where('event_id', $event->event_id)->lockForUpdate()->first();
            $event->refresh();

            $confirmedCount = EventReservation::where('event_id', $event->event_id)
                ->where('status', EventReservation::STATUS_CONFIRMED)
                ->count();

            $status = $confirmedCount < $event->capacity
                ? EventReservation::STATUS_CONFIRMED
                : EventReservation::STATUS_WAITLIST;

            $reservation = EventReservation::create([
                'event_id' => $event->event_id,
                'user_id' => $user->user_id,
                'status' => $status,
                'reserved_at' => now(),
            ]);

            EventAuditService::log('reservation.reserve', $status, [
                'event_id' => $event->event_id,
                'reservation_id' => $reservation->reservation_id,
            ], $user);

            $this->sendReservationMail($user, $event, $status);

            return ['reservation' => $reservation, 'status' => $status];
        });
    }

    public function cancel(Event $event, User $user): EventReservation
    {
        $reservation = EventReservation::where('event_id', $event->event_id)
            ->where('user_id', $user->user_id)
            ->whereIn('status', [EventReservation::STATUS_CONFIRMED, EventReservation::STATUS_WAITLIST])
            ->firstOrFail();

        return DB::transaction(function () use ($event, $reservation, $user) {
            Event::where('event_id', $event->event_id)->lockForUpdate()->first();

            $wasConfirmed = $reservation->status === EventReservation::STATUS_CONFIRMED;

            $reservation->update([
                'status' => EventReservation::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            EventAuditService::log('reservation.cancel', 'success', [
                'event_id' => $event->event_id,
                'reservation_id' => $reservation->reservation_id,
                'previous_status' => $wasConfirmed ? 'confirmed' : 'waitlist',
            ], $user);

            if ($wasConfirmed) {
                $this->promoteNextWaitlist($event);
            }

            app(\App\Services\NotificationScannerService::class)->notifyReservationCancelled($reservation->fresh(['event', 'user']));

            return $reservation->fresh();
        });
    }

    /**
     * @return list<EventReservation>
     */
    public function applyCapacityDecrease(Event $event, int $newCapacity): array
    {
        return DB::transaction(function () use ($event, $newCapacity) {
            Event::where('event_id', $event->event_id)->lockForUpdate()->first();

            $confirmed = EventReservation::where('event_id', $event->event_id)
                ->where('status', EventReservation::STATUS_CONFIRMED)
                ->orderByDesc('reserved_at')
                ->get();

            $demoted = [];
            $excess = max(0, $confirmed->count() - $newCapacity);

            foreach ($confirmed->take($excess) as $reservation) {
                $reservation->update(['status' => EventReservation::STATUS_WAITLIST]);
                $demoted[] = $reservation->fresh(['user']);

                EventAuditService::log('reservation.demote', 'waitlist', [
                    'event_id' => $event->event_id,
                    'reservation_id' => $reservation->reservation_id,
                    'reason' => 'capacity_decreased',
                    'new_capacity' => $newCapacity,
                ], $reservation->user);

                if ($reservation->user) {
                    Mail::to($reservation->user->email)->send(new EventDemotedToWaitlistMail($event, $reservation->user));
                }
            }

            return $demoted;
        });
    }

    /**
     * @return int Number of reservations cancelled
     */
    public function cancelAllForEvent(Event $event, User $actor): int
    {
        return DB::transaction(function () use ($event, $actor) {
            $reservations = EventReservation::where('event_id', $event->event_id)
                ->whereIn('status', [EventReservation::STATUS_CONFIRMED, EventReservation::STATUS_WAITLIST])
                ->with('user')
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => EventReservation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

                EventAuditService::log('reservation.cancel', 'cancelled_event', [
                    'event_id' => $event->event_id,
                    'reservation_id' => $reservation->reservation_id,
                ], $actor);

                if ($reservation->user) {
                    Mail::to($reservation->user->email)->send(new EventCancelledMail($event, $reservation->user));
                }
            }

            return $reservations->count();
        });
    }

    private function promoteNextWaitlist(Event $event): void
    {
        $confirmedCount = EventReservation::where('event_id', $event->event_id)
            ->where('status', EventReservation::STATUS_CONFIRMED)
            ->count();

        if ($confirmedCount >= $event->capacity) {
            return;
        }

        $next = EventReservation::where('event_id', $event->event_id)
            ->where('status', EventReservation::STATUS_WAITLIST)
            ->orderBy('reserved_at')
            ->first();

        if (! $next) {
            return;
        }

        $next->update(['status' => EventReservation::STATUS_CONFIRMED]);

        EventAuditService::log('reservation.promote', 'confirmed', [
            'event_id' => $event->event_id,
            'reservation_id' => $next->reservation_id,
        ], $next->user);

        if ($next->user) {
            Mail::to($next->user->email)->send(new EventWaitlistPromotedMail($event, $next->user));
        }
    }

    private function sendReservationMail(User $user, Event $event, string $status): void
    {
        if ($status === EventReservation::STATUS_CONFIRMED) {
            Mail::to($user->email)->send(new EventReservationConfirmedMail($event, $user));
        } else {
            Mail::to($user->email)->send(new EventReservationWaitlistMail($event, $user));
        }
    }
}
