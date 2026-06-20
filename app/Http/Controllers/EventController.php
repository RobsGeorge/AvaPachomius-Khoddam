<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use App\Services\EventAuditService;
use App\Services\EventCheckInService;
use App\Services\EventEligibilityService;
use App\Services\EventReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function __construct(
        private EventEligibilityService $eligibility,
        private EventReservationService $reservations,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $events = $this->eligibility->visibleEvents($user);

        return view('events.index', compact('events'));
    }

    public function show(Event $event)
    {
        $user = Auth::user();
        abort_unless($this->eligibility->canView($user, $event), 404);

        $reservation = EventReservation::where('event_id', $event->event_id)
            ->where('user_id', $user->user_id)
            ->whereIn('status', [EventReservation::STATUS_CONFIRMED, EventReservation::STATUS_WAITLIST])
            ->first();

        $qrUrl = null;
        if ($reservation && $reservation->status === EventReservation::STATUS_CONFIRMED) {
            $qrUrl = $event->signedCheckInUrlFor($user);
        }

        return view('events.show', compact('event', 'reservation', 'qrUrl'));
    }

    public function myReservations()
    {
        $user = Auth::user();
        $reservations = EventReservation::with('event')
            ->where('user_id', $user->user_id)
            ->orderByDesc('reserved_at')
            ->paginate(20);

        return view('events.my-reservations', compact('reservations'));
    }

    public function reserve(Event $event)
    {
        $user = Auth::user();

        try {
            $result = $this->reservations->reserve($event, $user);
            $message = $result['status'] === EventReservation::STATUS_CONFIRMED
                ? __('events.reserved_confirmed')
                : __('events.reserved_waitlist');

            return redirect()->route('events.show', $event->event_id)->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            EventAuditService::log('reservation.reserve', 'validation_failed', [
                'event_id' => $event->event_id,
                'message' => $e->getMessage(),
            ], $user);

            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Event $event)
    {
        $user = Auth::user();

        try {
            $this->reservations->cancel($event, $user);

            return redirect()->route('events.my-reservations')->with('success', __('events.cancelled_success'));
        } catch (\Throwable $e) {
            return back()->with('error', __('events.cancel_failed'));
        }
    }
}
