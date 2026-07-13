<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_user_id';

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonator(): ?User
    {
        if (! self::isActive()) {
            return null;
        }

        $user = User::find(session(self::SESSION_KEY));

        if (! $user || ! $user->is_superadmin) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        return $user;
    }

    public static function start(User $impersonator, User $target, Request $request): void
    {
        if (! $impersonator->is_superadmin) {
            abort(403);
        }

        if (ImpersonationService::isActive()) {
            throw ValidationException::withMessages([
                'user' => [__('pages.impersonate_already_active')],
            ]);
        }

        if (RolePreviewService::isActive()) {
            throw ValidationException::withMessages([
                'user' => [__('pages.impersonate_while_role_preview')],
            ]);
        }

        if ((int) $impersonator->user_id === (int) $target->user_id) {
            throw ValidationException::withMessages([
                'user' => [__('pages.impersonate_cannot_self')],
            ]);
        }

        if (! $target->registration_completed) {
            throw ValidationException::withMessages([
                'user' => [__('pages.impersonate_incomplete_registration')],
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $impersonator->user_id);

        Auth::login($target);
        $request->session()->regenerate();

        AuditLogService::logImpersonationEvent(
            $request,
            'impersonation_started',
            (int) $impersonator->user_id,
            (int) $target->user_id
        );
    }

    public static function stop(Request $request): User
    {
        $impersonatorId = session(self::SESSION_KEY);

        if (! $impersonatorId) {
            abort(403, __('pages.impersonate_not_active'));
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator || ! $impersonator->is_superadmin) {
            session()->forget(self::SESSION_KEY);
            abort(403, __('pages.impersonate_not_active'));
        }

        $targetUserId = Auth::id();

        Auth::login($impersonator);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        AuditLogService::logImpersonationEvent(
            $request,
            'impersonation_stopped',
            (int) $impersonator->user_id,
            (int) $targetUserId
        );

        return $impersonator;
    }

    /** @return list<string> */
    public static function roleSummary(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('role_name')->unique()->values()
            : $user->roles()->distinct()->pluck('role_name');

        $summary = $roles->all();

        if ($user->is_superadmin) {
            $summary[] = __('pages.superadmin_role');
        }

        return array_values(array_unique($summary));
    }
}
