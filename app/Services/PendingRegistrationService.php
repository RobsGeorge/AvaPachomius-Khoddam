<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserCourseRole;
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
}
