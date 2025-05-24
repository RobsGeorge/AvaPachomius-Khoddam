<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;

class OTPController extends Controller
{
    public function showForm(Request $request)
    {
        $userId = session('user_id') ?? $request->query('user_id');

        if (!$userId) return redirect()->route('login')->withErrors(['حدث خطأ.']);

        return view('auth.otp', compact('userId'));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
            'user_id' => 'required|exists:user,user_id',
        ]);

        $otpRecord = OtpCode::where('user_id', $request->user_id)
            ->where('code', $request->otp)
            ->where('expires_at', '>=', now())
            ->orderBy('expires_at', 'desc') // or ->latest('expires_at')
            ->first();


        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'رمز التحقق غير صالح أو منتهي الصلاحية.']);
        }

        $user = User::find($request->user_id);
        $user->is_verified = true;
        $user->save();

        // Delete OTP after success
        $otpRecord->delete();

        // Redirect to password creation form
        return redirect()->route('password.set', ['user_id' => $user->user_id]);
    }

    public function resend(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:user,user_id']);

        $key = 'resend-otp-' . $request->user_id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['resend' => 'يمكنك إعادة الإرسال مرة كل دقيقة.']);
        }

        $otp = rand(100000, 999999);
        OtpCode::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]
        );
        

        Mail::to(User::find($request->user_id)->email)->send(new \App\Mail\SendOTPEmail($otp));
        RateLimiter::hit($key, 60);

        return back()->with('success', 'تم إرسال رمز التحقق مجددًا.');
    }

    public function sendOtp(Request $request) //For tthe forgot Password Section
    {
        $request->validate([
            'email' => 'required|exists:user,email',
        ]);       
        // Check if the user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return redirect()->route('password.request')->withErrors(['email' => 'البريد الإلكتروني غير موجود.']);
        }
        // Get user_id from request or session
        $userId = $request->input('user_id');

        if (!$userId) {
            return redirect()->route('password.request')->withErrors(['email' => 'حدث خطأ، يرجى المحاولة مرة أخرى.']);
        }

        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('password.request')->withErrors(['email' => 'المستخدم غير موجود.']);
        }

        $userId = $user->user_id;
        return view('auth.otp', compact('userId'));

    }

}

?>
