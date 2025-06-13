<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OTPCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OTPController extends Controller
{
    public function showForm(Request $request)
    {
        $userId = session('user_id') ?? $request->query('user_id');
        if (!$userId) {
            return redirect()->route('register')->withErrors('يرجى تسجيل حساب أولاً.');
        }
        return view('auth.otp_verify', ['user_id' => $userId]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:user,id',
            'otp_code' => 'required|digits:6',
        ], [
            'otp_code.digits' => 'رمز التحقق يجب أن يتكون من 6 أرقام.',
        ]);

        $otpRecord = OTPCode::where('user_id', $request->user_id)
            ->where('code', $request->otp_code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp_code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.']);
        }

        // OTP valid, delete it
        $otpRecord->delete();

        // Redirect to set password page, pass user_id
        return redirect()->route('set.password', ['user_id' => $request->user_id]);
    }

    public function showSetPasswordForm(Request $request)
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return redirect()->route('register')->withErrors('حدث خطأ ما.');
        }
        return view('auth.set_password', ['user_id' => $userId]);
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::find($request->user_id);
        $user->password = Hash::make($request->password);
        $user->is_verified = true; // mark user as verified
        $user->save();

        // Login the user
        auth()->login($user);

        return redirect()->route('home')->with('status', 'تم إنشاء كلمة المرور وتفعيل الحساب بنجاح.');
    }
}
