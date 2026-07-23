<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\PendingRegistrationService;
use App\Support\PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token)
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => $request->query('email', old('email')),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => PasswordRules::field(),
        ], PasswordRules::messages());

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $wasPending = PendingRegistrationService::isPending($user);

                $user->forceFill([
                    'password'       => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                if ($wasPending) {
                    session([
                        PendingRegistrationService::SESSION_ENROLLMENT_USER_KEY => $user->user_id,
                    ]);
                }

                event(new PasswordReset($user));
            }
        );

        AuditLogService::setPasswordResult($request, [
            'success'        => $status === Password::PASSWORD_RESET,
            'failure_reason' => $status === Password::PASSWORD_RESET ? null : (string) $status,
        ]);

        if ($status === Password::PASSWORD_RESET) {
            $enrollmentUserId = session(PendingRegistrationService::SESSION_ENROLLMENT_USER_KEY);

            if ($enrollmentUserId) {
                return redirect()
                    ->route('register.enrollment', ['user_id' => $enrollmentUserId])
                    ->with('success', __('register.password_saved_continue_enrollment'));
            }

            return redirect()
                ->route('login')
                ->with('success', __('auth.password_reset_success'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
