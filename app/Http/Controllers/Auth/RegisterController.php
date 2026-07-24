<?php

namespace App\Http\Controllers\Auth;

use App\Database\LegacySchemaSync;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpCode;
use App\Models\UserCourseRole;
use App\Models\Course;
use App\Models\Role;
use App\Mail\SendOTPEmail;
use App\Services\AuditLogService;
use App\Services\PendingRegistrationService;
use App\Services\People\PersonDuplicateDetector;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use App\Support\PasswordRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        LegacySchemaSync::ensureRegistrationSchema();
        PendingRegistrationService::purgeStale();

        if (PendingRegistrationService::completedExists('email', $request->email)) {
            return back()
                ->withErrors(['email' => __('register.email_taken')])
                ->withInput();
        }

        $pendingUser = PendingRegistrationService::findPendingByEmail($request->email)
            ?? PendingRegistrationService::findPendingByMobile($request->mobile_number)
            ?? PendingRegistrationService::findPendingByNationalId($request->national_id);

        if ($pendingUser) {
            return $this->updateAndResendOtp($request, $pendingUser);
        }

        if (PendingRegistrationService::completedExists('mobile_number', $request->mobile_number)) {
            return back()
                ->withErrors(['mobile_number' => __('register.mobile_taken')])
                ->withInput();
        }

        if (PendingRegistrationService::completedExists('national_id', $request->national_id)) {
            return back()
                ->withErrors(['national_id' => __('register.national_id_taken')])
                ->withInput();
        }

        if (! $request->boolean('confirm_possible_duplicate')) {
            $matches = app(PersonDuplicateDetector::class)->findPossibleMatches([
                'first_name' => $request->first_name,
                'second_name' => $request->second_name,
                'third_name' => $request->third_name,
                'date_of_birth' => $request->date_of_birth,
                'mobile_number' => $request->mobile_number,
            ]);

            if ($matches->isNotEmpty()) {
                return view('auth.register-duplicate-confirm', [
                    'possibleMatches' => $matches,
                    'input' => $request->except(['profile_photo', '_token', 'password', 'password_confirmation']),
                ]);
            }
        }

        return $this->createAndSendOtp($request);
    }

    // ── Re-registration (unverified user exists) ──────────────────────────────

    private function updateAndResendOtp(Request $request, User $user)
    {
        if (PendingRegistrationService::completedExists('mobile_number', $request->mobile_number, $user->user_id)) {
            return back()
                ->withErrors(['mobile_number' => __('register.mobile_taken')])
                ->withInput();
        }

        if (PendingRegistrationService::completedExists('national_id', $request->national_id, $user->user_id)) {
            return back()
                ->withErrors(['national_id' => __('register.national_id_taken')])
                ->withInput();
        }

        if (PendingRegistrationService::completedExists('email', $request->email, $user->user_id)) {
            return back()
                ->withErrors(['email' => __('register.email_taken')])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $profilePhotoPath = $user->profile_photo;
            if ($request->hasFile('profile_photo')) {
                if ($profilePhotoPath) Storage::delete("public/{$profilePhotoPath}");
                $profilePhotoPath = $this->storeFile($request->file('profile_photo'), 'profile_photos');
            }

            $user->update(array_merge($this->userProfileAttributes($request, $profilePhotoPath), [
                'email' => $request->email,
            ]));

            OtpCode::where('user_id', $user->user_id)->delete();

            $otp = rand(100000, 999999);
            OtpCode::create([
                'user_id'    => $user->user_id,
                'code'       => $otp,
                'expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($user->email)->send(new SendOTPEmail($otp, $user));

            DB::commit();

            return PendingRegistrationService::redirectToOtpResume($user);

        } catch (QueryException $e) {
            DB::rollBack();
            $this->logRegistrationDbError('Re-registration', $e, $request);

            return back()
                ->withErrors($this->registrationDbErrors($e))
                ->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Re-registration failed: ' . $e->getMessage());
            return back()
                ->withErrors(['general' => $this->friendlyError($e)])
                ->withInput();
        }
    }

    // ── New user registration ─────────────────────────────────────────────────

    private function createAndSendOtp(Request $request)
    {
        // Profile photo is optional — store if uploaded, otherwise use empty string
        $profilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $this->storeFile($request->file('profile_photo'), 'profile_photos');
        }

        DB::beginTransaction();
        try {
            $user = User::create(array_merge($this->userProfileAttributes($request, $profilePhotoPath), [
                'email'                  => $request->email,
                'password'               => Hash::make(Str::random(32)),
                'is_verified'            => false,
                'is_superadmin'          => false,
                'registration_completed' => false,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]));

            $otp = rand(100000, 999999);
            OtpCode::create([
                'user_id'    => $user->user_id,
                'code'       => $otp,
                'expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($user->email)->send(new SendOTPEmail($otp, $user));

            DB::commit();

            return PendingRegistrationService::redirectToOtpResume($user);

        } catch (QueryException $e) {
            DB::rollBack();
            if ($profilePhotoPath) Storage::delete("public/{$profilePhotoPath}");
            $this->logRegistrationDbError('Registration', $e, $request);

            return back()
                ->withErrors($this->registrationDbErrors($e))
                ->withInput();

        } catch (\Throwable $e) {
            DB::rollBack();
            if ($profilePhotoPath) Storage::delete("public/{$profilePhotoPath}");
            Log::error('Registration failed: ' . $e->getMessage());

            return back()
                ->withErrors(['general' => $this->friendlyError($e)])
                ->withInput();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function userProfileAttributes(Request $request, ?string $profilePhotoPath = null): array
    {
        $attributes = [
            'first_name'    => $request->first_name,
            'second_name'   => $request->second_name,
            'third_name'    => $request->third_name,
            'national_id'   => $request->national_id,
            'mobile_number' => $request->mobile_number,
            'job'           => $request->job,
            'date_of_birth' => $request->date_of_birth,
            'profile_photo' => $profilePhotoPath ?? '',
        ];

        if (filled($profilePhotoPath)) {
            $attributes['profile_photo_uploaded_at'] = now(config('attendance.timezone', config('app.timezone')));
            $attributes['profile_photo_status'] = User::PHOTO_STATUS_PENDING;
            $attributes['profile_photo_reviewed_at'] = null;
            $attributes['profile_photo_reviewed_by_user_id'] = null;
            $attributes['profile_photo_rejection_note'] = null;
        }

        if (Schema::hasColumn('user', 'name')) {
            $attributes['name'] = User::fullNameFromParts(
                $request->first_name,
                $request->second_name,
                $request->third_name
            );
        }

        return $attributes;
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name'    => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'second_name'   => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'third_name'    => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'national_id'   => ['required', 'digits:14'],
            'email'         => ['required', 'email', 'max:255'],
            'job'           => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:'.now()->subYears(100)->format('Y-m-d')],
            'mobile_number' => ['required', 'numeric', 'digits:10'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'first_name.regex'       => 'الاسم الأول يجب أن يحتوي على أحرف عربية فقط.',
            'second_name.regex'      => 'الاسم الثاني يجب أن يحتوي على أحرف عربية فقط.',
            'third_name.regex'       => 'الاسم الثالث يجب أن يحتوي على أحرف عربية فقط.',
            'date_of_birth.before' => 'تاريخ الميلاد يجب أن يكون في الماضي.',
            'date_of_birth.after'  => 'تاريخ الميلاد غير صالح.',
            'mobile_number.digits' => 'رقم الهاتف يجب أن يكون 10 أرقام بالضبط.',
            'email.max'                    => __('register.email_too_long'),
        ]);
    }

    /** Map DB constraint violations to the correct form field (not always mobile). */
    private function mapDbConstraintError(QueryException $e): ?array
    {
        $message = $e->getMessage();
        $code    = $e->errorInfo[1] ?? null;

        $isDuplicate = $code === 1062
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');

        if ($isDuplicate) {
            if (str_contains($message, 'mobile_number')) {
                return ['mobile_number' => __('register.mobile_taken')];
            }
            if (str_contains($message, 'email')) {
                return ['email' => __('register.email_taken')];
            }
            if (str_contains($message, 'national_id')) {
                return ['national_id' => __('register.national_id_taken')];
            }

            return null;
        }

        $isTooLong = $code === 1406 || str_contains($message, 'Data too long');

        if ($isTooLong) {
            if (str_contains($message, 'mobile_number')) {
                return ['mobile_number' => __('register.phone_validation')];
            }
            if (str_contains($message, 'email')) {
                return ['email' => __('register.email_too_long')];
            }
            if (str_contains($message, 'national_id')) {
                return ['national_id' => __('register.national_id_hint')];
            }
            foreach (['first_name', 'second_name', 'third_name', 'job'] as $field) {
                if (str_contains($message, $field)) {
                    return [$field => __('register.field_too_long')];
                }
            }
        }

        return null;
    }

    /** User-facing errors for registration DB failures (field-specific when possible). */
    private function registrationDbErrors(QueryException $e): array
    {
        if ($mapped = $this->mapDbConstraintError($e)) {
            return $mapped;
        }

        $message = $e->getMessage();

        if (str_contains($message, "doesn't exist")
            || str_contains($message, 'Base table or view not found')) {
            return ['general' => __('register.db_setup_error')];
        }

        if (str_contains($message, 'Unknown column')
            || str_contains($message, 'cannot be null')
            || str_contains($message, "doesn't have a default value")) {
            return ['general' => __('register.db_schema_error')];
        }

        $general = __('register.db_error_generic');
        if (config('app.debug')) {
            $hint = $e->errorInfo[2] ?? $message;
            $general .= ' (' . Str::limit($hint, 160) . ')';
        }

        return ['general' => $general];
    }

    private function logRegistrationDbError(string $context, QueryException $e, Request $request): void
    {
        Log::error("{$context} DB error", [
            'email'       => $request->input('email'),
            'mobile'      => $request->input('mobile_number'),
            'sql_state'   => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
            'message'     => $e->getMessage(),
        ]);
    }

    protected function storeFile(UploadedFile $file, string $directory): string
    {
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs("public/{$directory}", $filename);
        return "{$directory}/{$filename}";
    }

    /** Map exception types to Arabic user-friendly messages */
    private function friendlyError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Connection refused')
            || str_contains($msg, 'SMTP')
            || str_contains($msg, 'Failed to authenticate')
            || str_contains($msg, 'Expected response code')
            || str_contains($msg, 'getaddrinfo')
            || $e instanceof \Symfony\Component\Mailer\Exception\TransportException) {
            return 'تعذر إرسال رمز التحقق بالبريد الإلكتروني. تأكد من صحة البريد الإلكتروني وحاول مرة أخرى، أو تواصل مع المشرف.';
        }

        return 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى أو التواصل مع المشرف.';
    }

    // ── Password setup ────────────────────────────────────────────────────────

    public function showSetPasswordForm(int|string $user_id)
    {
        $user = User::where('user_id', $user_id)->firstOrFail();

        if ($user->registration_completed) {
            return redirect()->route('login')->with('success', __('register.already_completed'));
        }

        if (session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY) != $user->user_id) {
            return redirect()
                ->route('otp.verify', ['user_id' => $user->user_id])
                ->withErrors(['general' => __('register.complete_otp_first')]);
        }

        return view('auth.set_password', compact('user_id'));
    }

    public function storePassword(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|exists:user,user_id',
            'password' => PasswordRules::field(),
        ], PasswordRules::messages());

        $user = User::where('user_id', $request->user_id)->firstOrFail();

        if ($user->registration_completed) {
            return redirect()->route('login')->with('success', __('register.already_completed'));
        }

        if (session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY) != $user->user_id) {
            return redirect()
                ->route('otp.verify', ['user_id' => $user->user_id])
                ->withErrors(['general' => __('register.complete_otp_first')]);
        }

        $user->password               = Hash::make($request->password);
        PendingRegistrationService::markCompleted($user);

        session()->forget(PendingRegistrationService::SESSION_PASSWORD_USER_KEY);

        AuditLogService::setPasswordResult($request, [
            'success' => true,
            'user_id' => $user->user_id,
            'email'   => $user->email,
        ]);

        return redirect()->route('login')->with('success', 'تم تعيين كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول.');
    }
}
