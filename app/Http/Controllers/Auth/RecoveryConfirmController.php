<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountRecoveryChallenge;
use App\Services\Auth\Recovery\CredentialChangeService;
use App\Services\Auth\Recovery\RecoveryOtpVerifier;
use Illuminate\Http\Request;

/**
 * Person-side OTP entry for admin-assisted / support recovery (no admin session).
 */
class RecoveryConfirmController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.recovery-confirm', [
            'challengeId' => $request->query('challenge'),
        ]);
    }

    public function store(
        Request $request,
        RecoveryOtpVerifier $verifier,
        CredentialChangeService $credentials,
    ) {
        $data = $request->validate([
            'challenge_id' => ['required', 'integer'],
            'otp' => ['required', 'digits:6'],
        ]);

        $challenge = AccountRecoveryChallenge::query()->findOrFail($data['challenge_id']);

        if (! in_array($challenge->tier, [
            AccountRecoveryChallenge::TIER_ADMIN_ASSISTED,
            AccountRecoveryChallenge::TIER_SUPPORT,
            AccountRecoveryChallenge::TIER_SELF_SERVE,
        ], true)) {
            return back()->withErrors(['otp' => __('auth.recovery_challenge_closed')]);
        }

        $result = $verifier->verify($challenge, $data['otp']);

        if ($result['status'] === 'advanced') {
            return back()->with('status', __('auth.recovery_otp_sent_asserted'));
        }

        $credentials->completeRebind($result['proof']);

        return redirect()
            ->route('login')
            ->with('success', __('auth.recovery_rebind_success'));
    }
}
