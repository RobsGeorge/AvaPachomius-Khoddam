<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\User;
use App\Services\EventEligibilityService;
use App\Services\EventReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private EventEligibilityService $eligibility,
        private EventReservationService $reservations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $events = $this->eligibility->visibleEvents($user);

        return response()->json([
            'data' => $events->map(fn (Event $event) => $this->serialize($event, $user))->values(),
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->eligibility->canView($user, $event), 404);

        return response()->json(['data' => $this->serialize($event, $user, true)]);
    }

    public function reserve(Request $request, Event $event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->eligibility->canReserve($user, $event), 403);

        try {
            $result = $this->reservations->reserve($event, $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $reservation = $result['reservation'] ?? null;

        return response()->json([
            'data' => [
                'status' => $result['status'] ?? null,
                'reservation' => $reservation ? [
                    'reservation_id' => $reservation->reservation_id ?? $reservation->getKey(),
                    'status' => $reservation->status,
                    'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                ] : null,
            ],
        ], 201);
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->reservations->cancel($event, $user);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('events.cancel_failed')], 422);
        }

        return response()->json(['message' => 'ok']);
    }

    public function myReservations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = EventReservation::with('event')
            ->where('user_id', $user->user_id)
            ->orderByDesc('reserved_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (EventReservation $r) => [
                'reservation_id' => $r->reservation_id ?? $r->getKey(),
                'status' => $r->status,
                'reserved_at' => $r->reserved_at?->toIso8601String(),
                'event' => $r->event ? $this->serialize($r->event, $user) : null,
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(Event $event, User $user, bool $detailed = false): array
    {
        $payload = [
            'event_id' => $event->event_id,
            'title' => $event->title,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'status' => $event->status,
            'can_reserve' => $this->eligibility->canReserve($user, $event),
        ];

        if ($detailed) {
            $payload['description'] = $event->description;
            $reservation = EventReservation::query()
                ->where('event_id', $event->event_id)
                ->where('user_id', $user->user_id)
                ->whereIn('status', [EventReservation::STATUS_CONFIRMED, EventReservation::STATUS_WAITLIST])
                ->first();
            $payload['my_reservation'] = $reservation ? [
                'reservation_id' => $reservation->reservation_id ?? $reservation->getKey(),
                'status' => $reservation->status,
            ] : null;
        }

        return $payload;
    }
}
