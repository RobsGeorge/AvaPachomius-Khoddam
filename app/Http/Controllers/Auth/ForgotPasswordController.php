<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountRecoveryChallenge;
use App\Models\User;
use App\Services\Auth\LoginResolutionService;
use App\Services\Auth\Recovery\AccountRecoveryService;
use App\Services\Auth\Recovery\CredentialChangeService;
use App\Services\Auth\Recovery\RecoveryOtpVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request, AccountRecoveryService $recovery)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower($request->input('email'));
        $throttleSeconds = (int) config('auth.passwords.users.throttle', 300);
        $rateLimitKey = 'password-reset:'.sha1($email);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return back()->with('status', __('auth.reset_link_sent'));
        }

        $result = $recovery->beginPasswordResetLink($email);

        RateLimiter::hit($rateLimitKey, $throttleSeconds);

        if (! ($result['ok'] ?? false)) {
            $reason = $result['reason'] ?? 'rejected';
            if ($reason === 'self_serve_blocked') {
                return back()->withErrors(['email' => __('auth.recovery_self_serve_blocked')]);
            }
            if ($reason === 'rate_limited') {
                return back()->withErrors(['email' => __('auth.recovery_rate_limited')]);
            }

            return back()->with('status', __('auth.reset_link_sent'));
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }

    public function showRebindForm()
    {
        return view('auth.recovery-rebind');
    }

    public function startSelfServeRebind(Request $request, AccountRecoveryService $recovery, LoginResolutionService $resolver)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'in:rebind_mobile,rebind_email'],
            'asserted_value' => ['required', 'string', 'max:255'],
        ]);

        $resolved = $resolver->resolve($data['identifier']);
        if (! $resolved) {
            return back()->with('status', __('auth.recovery_started_opaque'));
        }

        /** @var User $user */
        $user = $resolved['user'];
        $result = $recovery->beginSelfServeRebind($user, $data['purpose'], $data['asserted_value']);

        if (! ($result['ok'] ?? false)) {
            $reason = $result['reason'] ?? 'rejected';
            if ($reason === 'self_serve_blocked') {
                return back()->withErrors(['identifier' => __('auth.recovery_self_serve_blocked')]);
            }
            if ($reason === 'rate_limited') {
                return back()->withErrors(['identifier' => __('auth.recovery_rate_limited')]);
            }

            return back()->with('status', __('auth.recovery_started_opaque'));
        }

        session([
            'recovery_challenge_id' => $result['challenge']->account_recovery_challenge_id,
        ]);

        return redirect()
            ->route('recovery.otp.show')
            ->with('status', __('auth.recovery_otp_sent_proof'));
    }

    public function showRecoveryOtpForm()
    {
        if (! session('recovery_challenge_id')) {
            return redirect()->route('password.request');
        }

        return view('auth.recovery-otp');
    }

    public function verifyRecoveryOtp(
        Request $request,
        RecoveryOtpVerifier $verifier,
        CredentialChangeService $credentials,
    ) {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $challengeId = session('recovery_challenge_id');
        if (! $challengeId) {
            return redirect()->route('password.request');
        }

        $challenge = AccountRecoveryChallenge::query()->findOrFail($challengeId);
        $result = $verifier->verify($challenge, $data['otp']);

        if ($result['status'] === 'advanced') {
            return back()->with('status', __('auth.recovery_otp_sent_asserted'));
        }

        /** @var \App\Services\Auth\Recovery\PossessionProof $proof */
        $proof = $result['proof'];
        $credentials->completeRebind($proof);
        session()->forget('recovery_challenge_id');

        return redirect()
            ->route('login')
            ->with('success', __('auth.recovery_rebind_success'));
    }
}
