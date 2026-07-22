<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\CourseContextService;
use App\Services\RegistrationApplicationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
        private CourseContextService $courseContext,
    ) {}

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $attemptSucceeded = Auth::attempt($credentials, $request->boolean('remember'));
        $failureReason = null;
        $loginSucceeded = false;
        $redirectRoute = 'dashboard';

        if ($attemptSucceeded) {
            $request->session()->regenerate();
            $user = Auth::user();

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
                $redirectRoute = $this->courseContext->resolvePostLoginRoute($user);
            }
        } else {
            $failureReason = 'Invalid credentials';
        }

        AuditLogService::setPasswordResult($request, [
            'success'        => $loginSucceeded,
            'failure_reason' => $failureReason,
        ]);

        if ($loginSucceeded) {
            return redirect()->route($redirectRoute)->with('success', __('auth.login_success'));
        }

        if ($failureReason === 'Account not verified') {
            return back()->withErrors(['email' => __('auth.account_not_verified')]);
        }

        if ($failureReason === 'Not a church member') {
            return back()->withErrors(['email' => __('auth.not_a_church_member')]);
        }

        return back()->withErrors([
            'email' => __('auth.credentials_mismatch'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
