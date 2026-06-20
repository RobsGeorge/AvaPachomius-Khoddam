<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCheckIn;
use App\Models\EventReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventCheckInService
{
    /**
     * @return array{check_in: EventCheckIn, created: bool}
     */
    public function checkIn(Event $event, User $student, User $staff): array
    {
        if (! $event->isPublished() || $event->isCancelled()) {
            throw new \InvalidArgumentException(__('events.check_in_not_available'));
        }

        $now = now();
        if ($now->lt($event->starts_at) || $now->gt($event->ends_at)) {
            throw new \InvalidArgumentException(__('events.check_in_outside_window'));
        }

        $reservation = EventReservation::where('event_id', $event->event_id)
            ->where('user_id', $student->user_id)
            ->where('status', EventReservation::STATUS_CONFIRMED)
            ->first();

        if (! $reservation) {
            EventAuditService::log('check_in.record', 'denied', [
                'event_id' => $event->event_id,
                'student_user_id' => $student->user_id,
                'reason' => 'no_confirmed_reservation',
            ], $staff);

            throw new \InvalidArgumentException(__('events.no_confirmed_reservation'));
        }

        $existing = EventCheckIn::where('event_id', $event->event_id)
            ->where('user_id', $student->user_id)
            ->first();

        if ($existing) {
            EventAuditService::log('check_in.record', 'success', [
                'event_id' => $event->event_id,
                'check_in_id' => $existing->check_in_id,
                'duplicate' => true,
            ], $staff);

            return ['check_in' => $existing, 'created' => false];
        }

        $checkIn = DB::transaction(function () use ($event, $student, $staff, $reservation) {
            return EventCheckIn::create([
                'event_id' => $event->event_id,
                'user_id' => $student->user_id,
                'reservation_id' => $reservation->reservation_id,
                'checked_in_at' => now(),
                'checked_in_by_id' => $staff->user_id,
            ]);
        });

        EventAuditService::log('check_in.record', 'checked_in', [
            'event_id' => $event->event_id,
            'check_in_id' => $checkIn->check_in_id,
            'student_user_id' => $student->user_id,
        ], $staff);

        return ['check_in' => $checkIn, 'created' => true];
    }
}
