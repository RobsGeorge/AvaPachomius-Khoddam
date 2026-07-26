<?php

namespace App\Console\Commands;

use App\Models\AppointmentBooking;
use App\Models\ConfessionBooking;
use App\Models\UserNotificationPreference;
use App\Services\Pastoral\AppointmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * PAC4 — fire lead-time reminders for confirmed confession + pastoral bookings.
 */
class FireAppointmentBookingReminders extends Command
{
    protected $signature = 'pastoral:fire-booking-reminders {--lead-hours= : Override default lead hours}';

    protected $description = 'Send portal/email reminders for upcoming confession and pastoral bookings';

    public function handle(AppointmentNotificationService $notifier): int
    {
        $defaultLead = (int) ($this->option('lead-hours')
            ?: config('notifications.types.appointment_booking_reminder.defaults.config.lead_hours', 24));

        $sent = 0;
        $sent += $this->remindKind(
            ConfessionBooking::class,
            'confession',
            $defaultLead,
            $notifier,
        );
        $sent += $this->remindKind(
            AppointmentBooking::class,
            'appointment',
            $defaultLead,
            $notifier,
        );

        $this->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }

    private function remindKind(
        string $bookingClass,
        string $kind,
        int $defaultLead,
        AppointmentNotificationService $notifier,
    ): int {
        if (! Schema::hasTable((new $bookingClass)->getTable())) {
            return 0;
        }

        $sent = 0;
        $windowStart = now();
        $windowEnd = now()->addHours(max($defaultLead, 48));

        $bookings = $bookingClass::query()
            ->with(['slot', 'user'])
            ->where('status', $bookingClass::STATUS_CONFIRMED)
            ->whereHas('slot', function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('starts_at', [$windowStart, $windowEnd]);
            })
            ->get();

        foreach ($bookings as $booking) {
            $user = $booking->user;
            $slot = $booking->slot;
            if (! $user || ! $slot?->starts_at) {
                continue;
            }

            $lead = (int) (UserNotificationPreference::query()
                ->where('user_id', $user->user_id)
                ->where('type', 'appointment_booking_reminder')
                ->value('config->lead_hours') ?? $defaultLead);

            $target = $slot->starts_at->copy()->subHours(max(1, $lead));
            if (now()->lt($target) || now()->gt($target->copy()->addMinutes(30))) {
                continue;
            }

            $dedupeType = 'appointment_booking_reminder';
            $bookingId = $kind === 'confession'
                ? $booking->confession_booking_id
                : $booking->appointment_booking_id;
            $already = \App\Models\UserNotification::query()
                ->where('user_id', $user->user_id)
                ->where('type', $dedupeType)
                ->where('dedupe_key', "{$dedupeType}:{$kind}:{$bookingId}:user:{$user->user_id}")
                ->exists();
            if ($already) {
                continue;
            }

            $notifier->notifyReminder($booking, $kind);
            $sent++;
        }

        return $sent;
    }
}
