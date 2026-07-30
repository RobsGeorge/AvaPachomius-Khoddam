<?php

namespace App\Services\Pastoral;

use App\Models\AppointmentBooking;
use App\Models\ConfessionBooking;
use App\Models\Priest;
use App\Models\User;
use App\Support\Calendar\IcsWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * PAC5 — tokenized ICS feeds for the two pastoral calendars (confessions +
 * appointments). Feeds are polled by external calendar apps with no session,
 * so lookups authenticate via an opaque bearer token rather than auth/tenant
 * middleware — see docs/priest-appointment-calendar.md §10.
 */
class AppointmentIcsFeed
{
    public function __construct(
        private IcsWriter $writer,
    ) {}

    public function urlForMember(User $user): string
    {
        return route('ics.bookings', $this->tokenForMember($user));
    }

    public function tokenForMember(User $user): string
    {
        if (! $user->ics_bookings_token) {
            $user->forceFill(['ics_bookings_token' => $this->newToken()])->save();
        }

        return $user->ics_bookings_token;
    }

    public function regenerateForMember(User $user): string
    {
        $user->forceFill(['ics_bookings_token' => $this->newToken()])->save();

        return $user->ics_bookings_token;
    }

    public function urlForPriest(Priest $priest): string
    {
        return route('ics.priest-agenda', $this->tokenForPriest($priest));
    }

    public function tokenForPriest(Priest $priest): string
    {
        if (! $priest->ics_agenda_token) {
            $priest->forceFill(['ics_agenda_token' => $this->newToken()])->save();
        }

        return $priest->ics_agenda_token;
    }

    public function regenerateForPriest(Priest $priest): string
    {
        $priest->forceFill(['ics_agenda_token' => $this->newToken()])->save();

        return $priest->ics_agenda_token;
    }

    /** @return ?string null when the token matches no member (feed URL rendered stale/invalid). */
    public function icsForMemberToken(string $token): ?string
    {
        $user = User::query()->where('ics_bookings_token', $token)->first();
        if (! $user) {
            return null;
        }

        $items = collect()
            ->merge($this->confessionItemsForMember($user))
            ->merge($this->appointmentItemsForMember($user))
            ->sortBy(fn ($item) => $item['start']->getTimestamp())
            ->values()
            ->all();

        return $this->writer->render($items);
    }

    /** @return ?string null when the token matches no priest in the resolved tenant. */
    public function icsForPriestToken(string $token): ?string
    {
        $priest = Priest::query()->where('ics_agenda_token', $token)->first();
        if (! $priest) {
            return null;
        }

        $items = collect()
            ->merge($this->confessionItemsForPriest($priest))
            ->merge($this->appointmentItemsForPriest($priest))
            ->sortBy(fn ($item) => $item['start']->getTimestamp())
            ->values()
            ->all();

        return $this->writer->render($items);
    }

    private function confessionItemsForMember(User $user): Collection
    {
        return ConfessionBooking::query()
            ->with('slot.priest')
            ->where('user_id', $user->user_id)
            ->where('status', ConfessionBooking::STATUS_CONFIRMED)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', now()->subHour()))
            ->get()
            ->filter(fn (ConfessionBooking $b) => $b->slot !== null)
            ->map(fn (ConfessionBooking $booking) => [
                'uid' => 'confession-booking-'.$booking->confession_booking_id.'@khedma',
                'summary' => __('calendar.confession_with', ['priest' => $booking->slot->priest?->displayName() ?? '']),
                'start' => $booking->slot->starts_at,
                'end' => $booking->slot->ends_at,
                'all_day' => false,
                'location' => $booking->slot->location,
                'description' => $booking->notes,
            ]);
    }

    private function appointmentItemsForMember(User $user): Collection
    {
        return AppointmentBooking::query()
            ->with(['slot.priest', 'slot.type'])
            ->where('user_id', $user->user_id)
            ->where('status', AppointmentBooking::STATUS_CONFIRMED)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', now()->subHour()))
            ->get()
            ->filter(fn (AppointmentBooking $b) => $b->slot !== null)
            ->map(fn (AppointmentBooking $booking) => [
                'uid' => 'appointment-booking-'.$booking->appointment_booking_id.'@khedma',
                'summary' => __('calendar.appointment_with', [
                    'type' => $booking->slot->type?->displayName() ?? '',
                    'priest' => $booking->slot->priest?->displayName() ?? '',
                ]),
                'start' => $booking->slot->starts_at,
                'end' => $booking->slot->ends_at,
                'all_day' => false,
                'location' => $booking->slot->location,
                'description' => $booking->notes,
            ]);
    }

    private function confessionItemsForPriest(Priest $priest): Collection
    {
        return ConfessionBooking::query()
            ->with(['slot', 'user'])
            ->whereHas('slot', fn ($q) => $q->where('priest_id', $priest->priest_id)->where('starts_at', '>=', now()->subHour()))
            ->where('status', ConfessionBooking::STATUS_CONFIRMED)
            ->get()
            ->filter(fn (ConfessionBooking $b) => $b->slot !== null)
            ->map(fn (ConfessionBooking $booking) => [
                'uid' => 'confession-agenda-'.$booking->confession_booking_id.'@khedma',
                'summary' => __('calendar.confession_agenda_with', ['name' => $booking->user?->displayName() ?? '']),
                'start' => $booking->slot->starts_at,
                'end' => $booking->slot->ends_at,
                'all_day' => false,
                'location' => $booking->slot->location,
                'description' => $booking->notes,
            ]);
    }

    private function appointmentItemsForPriest(Priest $priest): Collection
    {
        return AppointmentBooking::query()
            ->with(['slot.type', 'user'])
            ->whereHas('slot', fn ($q) => $q->where('priest_id', $priest->priest_id)->where('starts_at', '>=', now()->subHour()))
            ->where('status', AppointmentBooking::STATUS_CONFIRMED)
            ->get()
            ->filter(fn (AppointmentBooking $b) => $b->slot !== null)
            ->map(fn (AppointmentBooking $booking) => [
                'uid' => 'appointment-agenda-'.$booking->appointment_booking_id.'@khedma',
                'summary' => __('calendar.appointment_agenda_with', [
                    'type' => $booking->slot->type?->displayName() ?? '',
                    'name' => $booking->user?->displayName() ?? '',
                ]),
                'start' => $booking->slot->starts_at,
                'end' => $booking->slot->ends_at,
                'all_day' => false,
                'location' => $booking->slot->location,
                'description' => $booking->notes,
            ]);
    }

    private function newToken(): string
    {
        return Str::random(48);
    }
}
