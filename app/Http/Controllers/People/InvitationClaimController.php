<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\OtpCode;
use App\Services\People\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationClaimController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::findPendingByPlainToken($token);
        if (! $invitation || ! $invitation->isAcceptable()) {
            if ($invitation && $invitation->isExpired()) {
                $invitation->forceFill(['status' => Invitation::STATUS_EXPIRED])->save();
            }

            return view('people_onboarding.claim-invalid');
        }

        return view('people_onboarding.claim', [
            'token' => $token,
            'invitation' => $invitation,
            'otpVerified' => session('invitation_otp_ok_'.$invitation->invitation_id) === true,
        ]);
    }

    public function verifyOtp(Request $request, string $token, InvitationService $invitations)
    {
        $invitation = Invitation::findPendingByPlainToken($token);
        if (! $invitation || ! $invitation->isAcceptable()) {
            return redirect()->route('invitations.claim', $token)
                ->withErrors(['otp' => __('people_onboarding.claim_invalid')]);
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $invitations->verifyOtp($invitation, $validated['otp'])) {
            return back()->withErrors(['otp' => __('people_onboarding.claim_otp_invalid')]);
        }

        session(['invitation_otp_ok_'.$invitation->invitation_id => true]);

        return redirect()->route('invitations.claim', $token);
    }

    public function accept(Request $request, string $token, InvitationService $invitations)
    {
        $invitation = Invitation::findPendingByPlainToken($token);
        if (! $invitation || ! $invitation->isAcceptable()) {
            return redirect()->route('invitations.claim', $token)
                ->withErrors(['password' => __('people_onboarding.claim_invalid')]);
        }

        if (session('invitation_otp_ok_'.$invitation->invitation_id) !== true) {
            return redirect()->route('invitations.claim', $token)
                ->withErrors(['otp' => __('people_onboarding.claim_otp_invalid')]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $invitations->accept($invitation, $validated['password'], true);
        session()->forget('invitation_otp_ok_'.$invitation->invitation_id);

        Auth::login($user);

        return redirect()->route('home')
            ->with('success', __('people_onboarding.claim_success'));
    }
}
