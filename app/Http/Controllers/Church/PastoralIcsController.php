<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Controller;
use App\Models\Priest;
use App\Services\Pastoral\AppointmentIcsFeed;
use App\Services\Pastoral\PriestDelegationService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * PAC5 — tokenized ICS feeds. The two `bookings`/`priestAgenda` endpoints are
 * intentionally outside the `auth` middleware: external calendar apps poll
 * them with no session, authenticating via the opaque token in the URL
 * instead. The regenerate actions are normal authenticated, permission-gated
 * actions.
 */
class PastoralIcsController extends Controller
{
    public function __construct(
        private AppointmentIcsFeed $feed,
        private PriestDelegationService $delegation,
    ) {}

    public function bookings(string $token): Response
    {
        $ics = $this->feed->icsForMemberToken($token);
        abort_if($ics === null, 404);

        return $this->icsResponse($ics, 'my-bookings.ics');
    }

    public function priestAgenda(string $token): Response
    {
        $ics = $this->feed->icsForPriestToken($token);
        abort_if($ics === null, 404);

        return $this->icsResponse($ics, 'priest-agenda.ics');
    }

    public function regenerateMyBookings()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->feed->regenerateForMember($user);

        return back()->with('success', __('church_mgmt.ics_link_regenerated'));
    }

    public function regeneratePriestAgenda(Priest $priest)
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless(
            $this->delegation->canManageConfessionSlots($user, $priest)
                || $this->delegation->canManageAppointmentSlots($user, $priest),
            403
        );

        $this->feed->regenerateForPriest($priest);

        return back()->with('success', __('church_mgmt.ics_link_regenerated'));
    }

    private function icsResponse(string $ics, string $filename): Response
    {
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
