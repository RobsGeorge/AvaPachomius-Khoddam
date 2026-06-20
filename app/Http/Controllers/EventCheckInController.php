<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\EventCheckInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class EventCheckInController extends Controller
{
    public function __construct(
        private EventCheckInService $checkIns,
    ) {}

    public function console(Event $event)
    {
        return view('events.check-in', compact('event'));
    }

    public function verify(Request $request, Event $event, User $user)
    {
        if (! $request->hasValidSignature()) {
            abort(403, __('events.check_in_link_expired'));
        }

        try {
            $result = $this->checkIns->checkIn($event, $user, Auth::user());

            return redirect()->route('events.admin.check-in', $event->event_id)
                ->with('success', $result['created']
                    ? __('events.check_in_success', ['name' => $user->first_name])
                    : __('events.check_in_already'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('events.admin.check-in', $event->event_id)
                ->with('error', $e->getMessage());
        }
    }

    public function record(Request $request, Event $event)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
        ]);

        $student = User::findOrFail($data['user_id']);

        try {
            $result = $this->checkIns->checkIn($event, $student, Auth::user());

            return back()->with('success', $result['created']
                ? __('events.check_in_success', ['name' => $student->first_name])
                : __('events.check_in_already'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
