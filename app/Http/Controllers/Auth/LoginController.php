<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Observability\ObservabilityRecorder;
use App\Services\Auth\LoginOtpChallengeService;
use App\Services\Auth\LoginResolutionService;
use App\Services\CourseContextService;
use App\Services\RegistrationApplicationService;
use App\Support\ChurchHost;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
        private CourseContextService $courseContext,
        private LoginResolutionService $resolution,
        private LoginOtpChallengeService $otpChallenge,
        private ObservabilityRecorder $observability,
    ) {}

    public function showLoginForm()
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $resolved = $this->resolution->resolve($validated['identifier']);

        if ($resolved === null) {
            return redirect()
                ->route('login.otp.show')
                ->with('status', __('auth.login_otp_sent_opaque'));
        }

        $user = $resolved['user'];
        $channel = $resolved['channel'];

        $issued = $this->otpChallenge->issue($user, $channel);

        if (! $issued['ok']) {
            if (($issued['reason'] ?? null) === 'rate_limited') {
                return back()->withErrors(['identifier' => __('auth.login_otp_rate_limited')]);
            }

            $this->recordAuthFailure('OTP send failed', $validated['identifier']);

            return back()->withErrors(['identifier' => __('auth.login_otp_send_failed')]);
        }

        $request->session()->put([
            LoginOtpChallengeService::SESSION_USER_KEY => $user->user_id,
            LoginOtpChallengeService::SESSION_CHANNEL_KEY => $channel,
        ]);

        return redirect()
            ->route('login.otp.show')
            ->with('status', __('auth.login_otp_sent'));
    }

    public function showOtpForm()
    {
        return response()
            ->view('auth.login-otp')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function verifyOtp(Request $request)
    {
        $userId = $request->session()->get(LoginOtpChallengeService::SESSION_USER_KEY);

        if (! $userId) {
            return redirect()
                ->route('login')
                ->withErrors(['identifier' => __('auth.login_otp_expired_session')]);
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if (! $user || ! $this->otpChallenge->verify($user, $validated['otp'])) {
            $this->recordAuthFailure('Invalid OTP', (string) $request->session()->get('login_identifier_hint', ''));

            return back()->withErrors(['otp' => __('auth.login_otp_invalid')]);
        }

        $channel = $request->session()->get(LoginOtpChallengeService::SESSION_CHANNEL_KEY);

        if ($channel === LoginResolutionService::CHANNEL_EMAIL && Schema::hasColumn('user', 'email_verified_at')) {
            $user->email_verified_at = now();
            $user->save();
        }

        $request->session()->forget([
            LoginOtpChallengeService::SESSION_USER_KEY,
            LoginOtpChallengeService::SESSION_CHANNEL_KEY,
        ]);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->completeLogin($request, $user);
    }

    public function resendOtp(Request $request)
    {
        $userId = $request->session()->get(LoginOtpChallengeService::SESSION_USER_KEY);

        if (! $userId) {
            return redirect()
                ->route('login')
                ->withErrors(['identifier' => __('auth.login_otp_expired_session')]);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        $channel = $request->session()->get(LoginOtpChallengeService::SESSION_CHANNEL_KEY);

        if (! $user || ! is_string($channel)) {
            return redirect()
                ->route('login')
                ->withErrors(['identifier' => __('auth.login_otp_expired_session')]);
        }

        $issued = $this->otpChallenge->issue($user, $channel);

        if (! $issued['ok']) {
            if (($issued['reason'] ?? null) === 'rate_limited') {
                return back()->withErrors(['otp' => __('auth.login_otp_rate_limited')]);
            }

            $this->recordAuthFailure('OTP send failed', $user->email);

            return back()->withErrors(['otp' => __('auth.login_otp_send_failed')]);
        }

        return back()->with('status', __('auth.login_otp_resent'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function completeLogin(Request $request, User $user)
    {
        $failureReason = null;
        $loginSucceeded = false;
        $redirectRoute = 'dashboard';

        if (! $user->registration_completed) {
            $failureReason = 'Account not verified';
            Auth::logout();
        } elseif (Schema::hasColumn('user', 'application_status') && ! $this->applications->isApproved($user)) {
            $loginSucceeded = true;
            $redirectRoute = $this->applications->redirectRouteFor($user);
        } elseif (! $user->is_verified) {
            $failureReason = 'Account not verified';
            Auth::logout();
        } elseif (config('tenancy.enabled')
            && ($church = TenantContext::current())
            && ! ($user->is_superadmin ?? false)
            && ! $user->belongsToChurch($church->church_id)
        ) {
            $failureReason = 'Not a church member';
            Auth::logout();
        } else {
            $loginSucceeded = true;
            if (($user->is_superadmin ?? false) && config('tenancy.enabled') && ChurchHost::isConsoleHost()) {
                $redirectRoute = 'superadmin.index';
            } else {
                $redirectRoute = $this->courseContext->resolvePostLoginRoute($user);
            }
        }

        if ($failureReason !== null) {
            $this->recordAuthFailure($failureReason, $user->email);
        }

        if ($loginSucceeded) {
            $params = $this->applications->redirectParamsFor($user);

            if (config('tenancy.enabled')
                && ChurchHost::isConsoleHost()
                && ! ($user->is_superadmin ?? false)
            ) {
                $church = $user->churches()
                    ->where('church.status', 'active')
                    ->wherePivot('status', 'active')
                    ->orderBy('church.name')
                    ->first();

                if (! $church) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors(['identifier' => __('auth.not_a_church_member')]);
                }

                $path = parse_url(route($redirectRoute, $params, false), PHP_URL_PATH) ?: '/dashboard';

                return redirect(ChurchHost::url($church, $path))
                    ->with('success', __('auth.login_success'))
                    ->with('info', __('workspace.use_church_portal'));
            }

            return redirect()->route($redirectRoute, $params)->with('success', __('auth.login_success'));
        }

        if ($failureReason === 'Account not verified') {
            return redirect()->route('login')->withErrors(['identifier' => __('auth.account_not_verified')]);
        }

        if ($failureReason === 'Not a church member') {
            return redirect()->route('login')->withErrors(['identifier' => __('auth.not_a_church_member')]);
        }

        return redirect()->route('login')->withErrors(['identifier' => __('auth.credentials_mismatch')]);
    }

    private function recordAuthFailure(string $reason, ?string $identifier): void
    {
        try {
            $this->observability->record('auth', 'warning', 'Login failure: '.$reason, [
                'failure_reason' => $reason,
                'identifier' => $identifier,
            ]);
        } catch (\Throwable) {
            //
        }
    }
}
