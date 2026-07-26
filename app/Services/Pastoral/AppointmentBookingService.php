<?php

namespace App\Services\Pastoral;

use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Pastoral\BookingRules;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function __construct(
        private AppointmentNotificationService $notifier,
    ) {}

    public function book(
        AppointmentSlot $slot,
        User $attendee,
        User $actor,
        ?string $notes = null,
        bool $onBehalf = false,
        bool $notify = true,
    ): AppointmentBooking {
        $this->assertSlotBookable($slot);
        $this->assertLeadTime($slot);

        if ($onBehalf && (int) $attendee->user_id === (int) $actor->user_id) {
            $onBehalf = false;
        }

        $this->assertActiveChurchMember($attendee, (int) $slot->church_id);

        $existing = AppointmentBooking::query()
            ->where('appointment_slot_id', $slot->appointment_slot_id)
            ->where('user_id', $attendee->user_id)
            ->first();

        if ($existing && $existing->status === AppointmentBooking::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.already_booked'),
            ]);
        }

        $payload = [
            'status' => AppointmentBooking::STATUS_CONFIRMED,
            'notes' => $notes,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'booked_by_user_id' => $actor->user_id,
        ];

        if ($existing) {
            $existing->update($payload);
            $booking = $existing->fresh();
        } else {
            $booking = new AppointmentBooking(array_merge($payload, [
                'appointment_slot_id' => $slot->appointment_slot_id,
                'user_id' => $attendee->user_id,
            ]));
            $booking->church_id = $slot->church_id;
            $booking->save();
        }

        AuditLogService::recordEvent('appointment_booking.created', [
            'appointment_booking_id' => $booking->appointment_booking_id,
            'appointment_slot_id' => $slot->appointment_slot_id,
            'user_id' => $attendee->user_id,
            'booked_by_user_id' => $actor->user_id,
            'on_behalf' => $onBehalf,
        ]);

        if ($notify) {
            $this->notifier->notifyConfirmed($booking, 'appointment');
        }

        return $booking;
    }

    public function updateNotes(AppointmentBooking $booking, ?string $notes): AppointmentBooking
    {
        $this->assertNotPastCutoff($booking->slot);
        $booking->update(['notes' => $notes]);
        AuditLogService::recordEvent('appointment_booking.updated', [
            'appointment_booking_id' => $booking->appointment_booking_id,
        ]);
        $this->notifier->notifyUpdated($booking->fresh(), 'appointment');

        return $booking->fresh();
    }

    public function cancel(AppointmentBooking $booking, User $actor, bool $notify = true): AppointmentBooking
    {
        if ($booking->status === AppointmentBooking::STATUS_CANCELLED) {
            return $booking;
        }

        $this->assertNotPastCutoff($booking->slot);
        $booking->update([
            'status' => AppointmentBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->user_id,
        ]);

        AuditLogService::recordEvent('appointment_booking.cancelled', [
            'appointment_booking_id' => $booking->appointment_booking_id,
            'cancelled_by_user_id' => $actor->user_id,
        ]);

        if ($notify) {
            $this->notifier->notifyCancelled($booking->fresh(), 'appointment');
        }

        return $booking->fresh();
    }

    public function reschedule(
        AppointmentBooking $booking,
        AppointmentSlot $newSlot,
        User $actor,
        ?string $notes = null,
    ): AppointmentBooking {
        $oldSlot = $booking->slot;
        abort_unless($oldSlot, 404);

        if ((int) $oldSlot->priest_id !== (int) $newSlot->priest_id) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.reschedule_same_priest'),
            ]);
        }
        if ((int) $oldSlot->appointment_type_id !== (int) $newSlot->appointment_type_id) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.reschedule_same_type'),
            ]);
        }
        if ((int) $booking->appointment_slot_id === (int) $newSlot->appointment_slot_id) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.reschedule_different_slot'),
            ]);
        }

        $this->assertNotPastCutoff($oldSlot);
        $this->assertSlotBookable($newSlot);
        $this->assertLeadTime($newSlot);

        if (AppointmentBooking::query()
            ->where('appointment_slot_id', $newSlot->appointment_slot_id)
            ->where('user_id', $booking->user_id)
            ->where('status', AppointmentBooking::STATUS_CONFIRMED)
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
        $new->update(['rescheduled_from_booking_id' => $booking->appointment_booking_id]);

        AuditLogService::recordEvent('appointment_booking.rescheduled', [
            'from_booking_id' => $booking->appointment_booking_id,
            'to_booking_id' => $new->appointment_booking_id,
        ]);

        $this->notifier->notifyRescheduled($booking, $new->fresh(), 'appointment');

        return $new->fresh();
    }

    private function assertSlotBookable(AppointmentSlot $slot): void
    {
        if (! $slot->isOpen()) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.slot_not_open')]);
        }
        if ($slot->remainingCapacity() <= 0) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.slot_full')]);
        }
    }

    private function assertLeadTime(AppointmentSlot $slot): void
    {
        $rules = $this->rulesForSlot($slot);
        if ($slot->starts_at && $slot->starts_at->lt(now()->addMinutes($rules->minLeadMinutes))) {
            throw ValidationException::withMessages(['slot' => __('church_mgmt.booking_too_soon')]);
        }
    }

    private function assertNotPastCutoff(?AppointmentSlot $slot): void
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

    private function rulesForSlot(AppointmentSlot $slot): BookingRules
    {
        $church = Church::find($slot->church_id);

        return $church ? BookingRules::for($church) : new BookingRules(
            BookingRules::DEFAULT_TIMEZONE,
            BookingRules::DEFAULT_MIN_LEAD_MINUTES,
            BookingRules::DEFAULT_CANCEL_CUTOFF_MINUTES,
        );
    }
}
