<?php

namespace App\Services\Pastoral;

use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationGeneratorService;
use Illuminate\Support\Facades\Schema;

/**
 * PAC4 — lifecycle notifications for confession + pastoral bookings (portal/email).
 * WhatsApp stays off until Contact Verification gates exist (mobile_verified_at).
 */
class AppointmentNotificationService
{
    public function __construct(
        private NotificationGeneratorService $generator,
    ) {}

    public function notifyConfirmed(ConfessionBooking|AppointmentBooking $booking, string $kind): void
    {
        $this->notifyParties($booking, $kind, 'appointment_booking_confirmed');
    }

    public function notifyUpdated(ConfessionBooking|AppointmentBooking $booking, string $kind): void
    {
        $this->notifyParties($booking, $kind, 'appointment_booking_updated');
    }

    public function notifyCancelled(ConfessionBooking|AppointmentBooking $booking, string $kind): void
    {
        $this->notifyParties($booking, $kind, 'appointment_booking_cancelled');
    }

    public function notifyRescheduled(
        ConfessionBooking|AppointmentBooking $from,
        ConfessionBooking|AppointmentBooking $to,
        string $kind,
    ): void {
        $this->notifyParties($to, $kind, 'appointment_booking_rescheduled', [
            'from_booking_id' => $this->bookingId($from),
            'from_starts_at' => $from->slot?->starts_at?->toIso8601String(),
        ]);
    }

    public function notifyReminder(ConfessionBooking|AppointmentBooking $booking, string $kind): void
    {
        $this->notifyParties($booking, $kind, 'appointment_booking_reminder');
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    private function notifyParties(
        ConfessionBooking|AppointmentBooking $booking,
        string $kind,
        string $type,
        array $extraMeta = [],
    ): void {
        $booking->loadMissing(['slot.priest.user', 'user']);
        $slot = $booking->slot;
        if (! $slot) {
            return;
        }

        $starts = $slot->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '';
        $priestName = $slot->priest?->displayName() ?? '';
        $attendee = $booking->user;
        $actionUrl = $kind === 'confession'
            ? route('church.confession.my-bookings')
            : route('church.appointments.my-bookings');

        $title = __('notifications.generated.'.$type.'_title', [
            'kind' => __('church_mgmt.kind_'.$kind),
            'when' => $starts,
        ]);
        $body = __('notifications.generated.'.$type.'_body', [
            'kind' => __('church_mgmt.kind_'.$kind),
            'when' => $starts,
            'priest' => $priestName,
        ]);

        $recipients = collect();
        if ($attendee) {
            $recipients->push($attendee);
        }
        $priestUser = $slot->priest?->user;
        if ($priestUser) {
            $recipients->push($priestUser);
        }
        foreach ($this->activeSecretaries($slot->priest) as $sec) {
            $recipients->push($sec);
        }

        $sourceClass = $booking instanceof ConfessionBooking
            ? ConfessionBooking::class
            : AppointmentBooking::class;
        $sourceId = $this->bookingId($booking);

        foreach ($recipients->unique('user_id') as $user) {
            $this->generator->createOrUpdate(
                $user,
                $type,
                $title,
                $body,
                $actionUrl,
                $sourceClass,
                $sourceId,
                UserNotification::PRIORITY_NORMAL,
                array_merge([
                    'kind' => $kind,
                    'booking_id' => $sourceId,
                    'priest_id' => $slot->priest_id,
                    'starts_at' => $slot->starts_at?->toIso8601String(),
                ], $extraMeta),
                "{$type}:{$kind}:{$sourceId}:user:{$user->user_id}",
            );
        }
    }

    /** @return list<User> */
    private function activeSecretaries(?Priest $priest): array
    {
        if (! $priest || ! Schema::hasTable('priest_secretary')) {
            return [];
        }

        return PriestSecretary::query()
            ->where('priest_id', $priest->priest_id)
            ->where('status', PriestSecretary::STATUS_ACTIVE)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->all();
    }

    private function bookingId(ConfessionBooking|AppointmentBooking $booking): int
    {
        return (int) ($booking instanceof ConfessionBooking
            ? $booking->confession_booking_id
            : $booking->appointment_booking_id);
    }
}
