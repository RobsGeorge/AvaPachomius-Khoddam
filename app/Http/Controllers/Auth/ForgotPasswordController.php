<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    // Show the form to request a password reset link
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    // Handle sending the reset link email
    public function sendResetLinkEmail(Request $request)
    {
        // Validate email
        $request->validate(['email' => 'required|email']);

        // Send the reset link
        /*
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Return response based on success or failure
        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => __($status)])
                    : back()->withErrors(['email' => __($status)]);
    */
    
     return view('auth.passwords.contact');
    }
}
