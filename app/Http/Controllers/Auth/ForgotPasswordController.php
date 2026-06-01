<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower($request->input('email'));
        $throttleSeconds = (int) config('auth.passwords.users.throttle', 300);
        $rateLimitKey = 'password-reset:' . sha1($email);

        if (! RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                Password::sendResetLink(['email' => $request->email]);
            }

            RateLimiter::hit($rateLimitKey, $throttleSeconds);
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }
}
