<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\Course;
use App\Models\Role;
use App\Models\RegistrationApplication;
use App\Models\RegistrationReviewTemplate;
use App\Services\RegistrationApplicationService;
use App\Services\RegistrationReviewMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PendingRegistrationService
{
    public const SESSION_PASSWORD_USER_KEY = 'registration_password_user_id';

    public static function isPending(User $user): bool
    {
        if (! Schema::hasColumn('user', 'registration_completed')) {
            return ! (bool) $user->is_verified;
        }

        return ! (bool) $user->registration_completed;
    }

    public static function findPendingByEmail(string $email): ?User
    {
        $user = User::where('email', $email)->first();

        return $user && self::isPending($user) ? $user : null;
    }

    public static function findPendingByMobile(string $mobile): ?User
    {
        $user = User::where('mobile_number', $mobile)->first();

        return $user && self::isPending($user) ? $user : null;
    }

    public static function findPendingByNationalId(string $nationalId): ?User
    {
        $user = User::where('national_id', $nationalId)->first();

        return $user && self::isPending($user) ? $user : null;
    }

    public static function completedExists(string $column, string $value, ?int $exceptUserId = null): bool
    {
        $query = User::where($column, $value);

        if (Schema::hasColumn('user', 'registration_completed')) {
            $query->where('registration_completed', true);
        } else {
            $query->where('is_verified', true);
        }

        if ($exceptUserId !== null) {
            $query->where('user_id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    /** Remove abandoned sign-ups that never finished password setup. */
    public static function purgeStale(int $hours = 72): int
    {
        if (! Schema::hasColumn('user', 'registration_completed')) {
            return 0;
        }

        $query = User::query()->where('registration_completed', false);

        if (! Schema::hasColumn('user', 'created_at')) {
            return 0;
        }

        $query->where('created_at', '<', now()->subHours($hours));

        $purged = 0;

        foreach ($query->get() as $user) {
            self::deletePending($user);
            $purged++;
        }

        return $purged;
    }

    public static function deletePending(User $user): void
    {
        if (! self::isPending($user)) {
            return;
        }

        OtpCode::where('user_id', $user->user_id)->delete();
        UserCourseRole::where('user_id', $user->user_id)->delete();

        if ($user->profile_photo) {
            Storage::delete("public/{$user->profile_photo}");
        }

        $user->delete();
    }

    public static function redirectToOtpResume(User $user, bool $resent = true)
    {
        return redirect()
            ->route('otp.verify', ['user_id' => $user->user_id])
            ->with('user_id', $user->user_id)
            ->with('pending_registration_resume', true)
            ->with(
                'success',
                $resent ? __('register.pending_otp_resent') : __('register.pending_otp_resume')
            );
    }

    /** Mark signup complete and queue the application for admin review. */
    public static function markCompleted(User $user): void
    {
        $user->is_verified = false;

        if (Schema::hasColumn('user', 'registration_completed')) {
            $user->registration_completed = true;
        }

        if (Schema::hasColumn('user', 'application_status')) {
            $user->application_status = RegistrationApplication::STATUS_PENDING_REVIEW;
        } else {
            $user->is_verified = true;
        }

        if (Schema::hasColumn('user', 'created_at') && $user->created_at === null) {
            $user->created_at = now();
        }

        if (Schema::hasColumn('user', 'updated_at')) {
            $user->updated_at = now();
        }

        $user->save();

        if (Schema::hasColumn('user', 'application_status')) {
            app(RegistrationApplicationService::class)->createFromUser($user);
            app(RegistrationReviewMailService::class)->send(
                $user,
                RegistrationReviewTemplate::KEY_RECEIVED
            );
        } else {
            self::assignDefaultStudentRole($user);
        }
    }

    /** @return array{key: string, label: string, hint: string|null} */
    public static function unknownAccountStatus(): array
    {
        return [
            'key' => 'unknown',
            'label' => '—',
            'hint' => null,
        ];
    }

    /** @return array{key: string, label: string, hint: string|null} */
    public static function accountStatus(User $user): array
    {
        if (! self::isPending($user)) {
            return [
                'key' => 'active',
                'label' => __('pages.account_status_active'),
                'hint' => null,
            ];
        }

        $hasPendingOtp = Schema::hasTable('otp_code')
            && OtpCode::where('user_id', $user->user_id)
                ->where('expires_at', '>', now())
                ->exists();

        if ($hasPendingOtp) {
            return [
                'key' => 'pending_otp',
                'label' => __('pages.account_status_pending_otp'),
                'hint' => __('pages.account_status_pending_otp_hint'),
            ];
        }

        return [
            'key' => 'incomplete',
            'label' => __('pages.account_status_incomplete'),
            'hint' => __('pages.account_status_incomplete_hint'),
        ];
    }

    public static function assignDefaultStudentRole(User $user): void
    {
        try {
            $defaultCourse = Course::find(1);

            if (! $defaultCourse) {
                return;
            }

            $studentRole = Role::studentRoleForCourse($defaultCourse->course_id)
                ?? Role::query()
                    ->whereNull('course_id')
                    ->where('slug', 'student')
                    ->where('is_template', true)
                    ->first();

            if (! $studentRole) {
                return;
            }

            $alreadyAssigned = UserCourseRole::where([
                'user_id' => $user->user_id,
                'course_id' => 1,
                'role_id' => $studentRole->role_id,
            ])->exists();

            if (! $alreadyAssigned) {
                UserCourseRole::create([
                    'user_id' => $user->user_id,
                    'course_id' => 1,
                    'role_id' => $studentRole->role_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Student role assignment skipped during registration', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
