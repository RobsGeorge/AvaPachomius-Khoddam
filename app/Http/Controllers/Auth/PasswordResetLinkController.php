<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class PasswordResetLinkController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user with this email exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // We do NOT reveal if email doesn't exist, just flash the same message
            return redirect()->back()->with('status', 'إذا تم العثور على البريد الإلكتروني في قاعدة بياناتنا، سوف نرسل لك رسالة. يرجى التحقق من صندوق الرسائل غير المرغوب فيها أيضًا.');
        }

        // Redirect to OTP sending route (you can pass email or user_id)
        // Let's assume OTP controller expects user_id to send OTP
        return redirect()->route('otp.send')->with([
            'user_id' => $user->id,
            'status' => 'إذا تم العثور على البريد الإلكتروني في قاعدة بياناتنا، سوف نرسل لك رسالة. يرجى التحقق من صندوق الرسائل غير المرغوب فيها أيضًا.',
        ]);
    }
}

?>