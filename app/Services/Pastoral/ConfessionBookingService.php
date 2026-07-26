<?php

namespace App\Services\Pastoral;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Pastoral\BookingRules;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConfessionBookingService
{
    public function __construct(
        private AppointmentNotificationService $notifier,
    ) {}

    public function book(
        ConfessionSlot $slot,
        User $attendee,
        User $actor,
        ?string $notes = null,
        bool $onBehalf = false,
        bool $notify = true,
    ): ConfessionBooking {
        $this->assertSlotBookable($slot);
        $this->assertLeadTime($slot);

        if ($onBehalf && (int) $attendee->user_id === (int) $actor->user_id) {
            $onBehalf = false;
        }

        $this->assertActiveChurchMember($attendee, (int) $slot->church_id);

        $existing = ConfessionBooking::query()
            ->where('confession_slot_id', $slot->confession_slot_id)
            ->where('user_id', $attendee->user_id)
            ->first();

        if ($existing && $existing->status === ConfessionBooking::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.already_booked'),
            ]);
        }

        $payload = [
            'status' => ConfessionBooking::STATUS_CONFIRMED,
            'notes' => $notes,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
        ];

        if (Schema::hasColumn('confession_booking', 'booked_by_user_id')) {
            $payload['booked_by_user_id'] = $actor->user_id;
        }

        if ($existing) {
            $existing->update($payload);
            $booking = $existing->fresh();
        } else {
            $booking = new ConfessionBooking(array_merge($payload, [
                'confession_slot_id' => $slot->confession_slot_id,
                'user_id' => $attendee->user_id,
            ]));
            $booking->church_id = $slot->church_id;
            $booking->save();
        }

        AuditLogService::recordEvent('confession_booking.created', [
            'confession_booking_id' => $booking->confession_booking_id,
            'confession_slot_id' => $slot->confession_slot_id,
            'user_id' => $attendee->user_id,
            'booked_by_user_id' => $actor->user_id,
            'on_behalf' => $onBehalf,
        ]);

        if ($notify) {
            $this->notifier->notifyConfirmed($booking, 'confession');
        }

        return $booking;
    }

    public function updateNotes(ConfessionBooking $booking, ?string $notes): ConfessionBooking
    {
        $this->assertNotPastCutoff($booking->slot);
        $booking->update(['notes' => $notes]);
        AuditLogService::recordEvent('confession_booking.updated', [
            'confession_booking_id' => $booking->confession_booking_id,
        ]);
        $this->notifier->notifyUpdated($booking->fresh(), 'confession');

        return $booking->fresh();
    }

    public function cancel(ConfessionBooking $booking, User $actor, bool $notify = true): ConfessionBooking
    {
        if ($booking->status === ConfessionBooking::STATUS_CANCELLED) {
            return $booking;
        }

        $this->assertNotPastCutoff($booking->slot);

        $data = ['status' => ConfessionBooking::STATUS_CANCELLED];
        if (Schema::hasColumn('confession_booking', 'cancelled_at')) {
            $data['cancelled_at'] = now();
            $data['cancelled_by_user_id'] = $actor->user_id;
        }
        $booking->update($data);

        AuditLogService::recordEvent('confession_booking.cancelled', [
            'confession_booking_id' => $booking->confession_booking_id,
            'cancelled_by_user_id' => $actor->user_id,
        ]);

        if ($notify) {
            $this->notifier->notifyCancelled($booking->fresh(), 'confession');
        }

        return $booking->fresh();
    }

    public function reschedule(
        ConfessionBooking $booking,
        ConfessionSlot $newSlot,
        User $actor,
        ?string $notes = null,
    ): ConfessionBooking {
        $oldSlot = $booking->slot;
        abort_unless($oldSlot, 404);

        if ((int) $oldSlot->priest_id !== (int) $newSlot->priest_id) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.reschedule_same_priest'),
            ]);
        }
        if ((int) $booking->confession_slot_id === (int) $newSlot->confession_slot_id) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.reschedule_different_slot'),
            ]);
        }

        $this->assertNotPastCutoff($oldSlot);
        $this->assertSlotBookable($newSlot);
        $this->assertLeadTime($newSlot);

        if (ConfessionBooking::query()
            ->where('confession_slot_id', $newSlot->confession_slot_id)
            ->where('user_id', $booking->user_id)
            ->where('status', ConfessionBooking::STATUS_CONFIRMED)
            ->exists()) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.already_booked'),
            ]);
        }

        $this->cancel($booking, $actor, notify: false);
        $new = $this->book(
            $newSlot,
            User::findOrFail($booking->user_id),
            $actor,
            $notes ?? $booking->notes,
            onBehalf: (int) $actor->user_id !== (int) $booking->user_id,
            notify: false,
        );

        if (Schema::hasColumn('confession_booking', 'rescheduled_from_booking_id')) {
            $new->update(['rescheduled_from_booking_id' => $booking->confession_booking_id]);
        }

        AuditLogService::recordEvent('confession_booking.rescheduled', [
            'from_booking_id' => $booking->confession_booking_id,
            'to_booking_id' => $new->confession_booking_id,
        ]);

        $this->notifier->notifyRescheduled($booking, $new->fresh(), 'confession');

        return $new->fresh();
    }

    private function assertSlotBookable(ConfessionSlot $slot): void
    {
        if (! $slot->isOpen()) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.slot_not_open')]);
        }
        if ($slot->remainingCapacity() <= 0) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.slot_full')]);
        }
    }

    private function assertLeadTime(ConfessionSlot $slot): void
    {
        $rules = $this->rulesForSlot($slot);
        if ($slot->starts_at && $slot->starts_at->lt(now()->addMinutes($rules->minLeadMinutes))) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.booking_too_soon')]);
        }
    }

    private function assertNotPastCutoff(?ConfessionSlot $slot): void
    {
        if (! $slot?->starts_at) {
            return;
        }
        $rules = $this->rulesForSlot($slot);
        if (now()->gte($slot->starts_at->copy()->subMinutes($rules->cancelCutoffMinutes))) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.booking_cutoff_passed')]);
        }
    }

    private function assertActiveChurchMember(User $user, int $churchId): void
    {
        $ok = ChurchUser::query()
            ->where('church_id', $churchId)
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['user_id' => __('church_mgmt.member_required')]);
        }
    }

    private function rulesForSlot(ConfessionSlot $slot): BookingRules
    {
        $church = Church::find($slot->church_id);

        return $church ? BookingRules::for($church) : new BookingRules(
            BookingRules::DEFAULT_TIMEZONE,
            BookingRules::DEFAULT_MIN_LEAD_MINUTES,
            BookingRules::DEFAULT_CANCEL_CUTOFF_MINUTES,
        );
    }
}
