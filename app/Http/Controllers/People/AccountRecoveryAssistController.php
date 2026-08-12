<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\User;
use App\Services\Auth\Recovery\AdminAssistedRecoveryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountRecoveryAssistController extends Controller
{
    public function create(Person $person): View
    {
        $this->authorize('people.recovery.assist');

        $user = User::query()->where('person_id', $person->person_id)->first();

        return view('people.recovery-assist', [
            'person' => $person,
            'user' => $user,
        ]);
    }

    public function store(Request $request, Person $person, AdminAssistedRecoveryService $assisted)
    {
        $this->authorize('people.recovery.assist');

        $data = $request->validate([
            'purpose' => ['required', 'in:rebind_mobile,rebind_email'],
            'asserted_value' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('person_id', $person->person_id)->firstOrFail();

        $result = $assisted->vouchAndSendOtp(
            $request->user(),
            $user,
            $data['purpose'],
            $data['asserted_value'],
        );

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors([
                'asserted_value' => __('auth.recovery_'.$result['reason']),
            ]);
        }

        return redirect()
            ->route('people.show', $person)
            ->with('success', __('auth.recovery_admin_vouched', [
                'challenge' => $result['challenge']->account_recovery_challenge_id,
            ]));
    }
}
