<?php
// app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
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

        if ($attemptSucceeded) {
            $request->session()->regenerate();

            if (! Auth::user()->is_verified || ! Auth::user()->registration_completed) {
                $failureReason = 'Account not verified';
                Auth::logout();
            } else {
                $loginSucceeded = true;
            }
        } else {
            $failureReason = 'Invalid credentials';
        }

        AuditLogService::setPasswordResult($request, [
            'success'        => $loginSucceeded,
            'failure_reason' => $failureReason,
        ]);

        if ($loginSucceeded) {
            return redirect('/dashboard')->with('success', 'Welcome back!');
        }

        if ($failureReason === 'Account not verified') {
            return back()->withErrors(['email' => 'حسابك لم يتم التحقق منه بعد. يرجى التواصل مع المشرف.']);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
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

?>