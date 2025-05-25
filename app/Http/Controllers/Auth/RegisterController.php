<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Mail\SendOTPEmail;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $profilePhotoPath = $this->storeFile($request->file('profile_photo'), 'profile_photos');

        DB::beginTransaction();

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'second_name' => $request->second_name,
                'third_name' => $request->third_name,
                'national_id' => $request->national_id,
                'mobile_number' => $request->mobile_number,
                'email' => $request->email,
                'job' => $request->job,
                'date_of_birth' => $request->date_of_birth,
                'profile_photo' => $profilePhotoPath,
                'password' => Hash::make(Str::random(12)),
                'is_verified' => false,
            ]);

            $studentRole = Role::where('role_name', 'Student')->first();

            if (!$studentRole) {
                throw new \Exception('Student role not found.');
            }

            $user->roles()->attach($studentRole->id);

            $otp = rand(100000, 999999);
            OtpCode::create([
                'user_id' => $user->user_id,
                'code' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($user->email)->send(new SendOTPEmail($otp));

            DB::commit();
            return redirect()->route('otp.verify')->with([
                'user_id' => $user->user_id,
                'otp' => $otp,
                'success' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني. يرجى التحقق منه.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Storage::delete("public/{$profilePhotoPath}");

            \Log::error('Registration failed: ' . $e->getMessage());

            // Show a custom error view with a friendly message
            return response()->view('errors.registration', [
                'message' => 'حدث خطأ أثناء عملية التسجيل. الرجاء المحاولة مرة أخرى.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'second_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'third_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'national_id' => ['required', 'digits:14'],
            'email' => ['required', 'email', 'max:100', 'unique:user,email'],
            'job' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date'],
            'mobile_number' => ['required'],
            'profile_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'first_name.regex' => 'الاسم الأول يجب أن يحتوي على أحرف عربية فقط.',
            'second_name.regex' => 'الاسم الثاني يجب أن يحتوي على أحرف عربية فقط.',
            'third_name.regex' => 'الاسم الثالث يجب أن يحتوي على أحرف عربية فقط.',
            'mobile_number.regex' => 'رقم الهاتف يجب أن يحتوي على 10 أرقام ويبدأ بـ 10.',
        ]);
    }

    protected function storeFile($file, $directory)
    {
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs("public/{$directory}", $filename);
        return "{$directory}/{$filename}";
    }

    public function showSetPasswordForm($user_id)
{
    return view('auth.set_password', compact('user_id'));
}

    public function storePassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:user,user_id',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('user_id', $request->user_id)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('login')->with('success', 'تم تعيين كلمة المرور بنجاح!');
    }
}
?>