<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Observability\ObservabilityRecorder;
use App\Services\Auth\LoginOtpChallengeService;
use App\Services\Auth\LoginResolutionService;
use App\Services\RegistrationApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
        private LoginResolutionService $resolution,
        private LoginOtpChallengeService $otpChallenge,
        private ObservabilityRecorder $observability,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $identifier = $validated['identifier'] ?? $validated['email'] ?? null;

        if (! filled($identifier)) {
            throw ValidationException::withMessages([
                'identifier' => [__('validation.required', ['attribute' => 'identifier'])],
            ]);
        }

        $resolved = $this->resolution->resolve($identifier);

        if ($resolved === null) {
            return response()->json([
                'message' => __('auth.login_otp_sent_opaque'),
            ]);
        }

        $issued = $this->otpChallenge->issue($resolved['user'], $resolved['channel']);

        if (! $issued['ok']) {
            if (($issued['reason'] ?? null) === 'rate_limited') {
                throw ValidationException::withMessages([
                    'identifier' => [__('auth.login_otp_rate_limited')],
                ]);
            }

            $this->recordAuthFailure('OTP send failed', $identifier, $resolved['user']->user_id);

            throw ValidationException::withMessages([
                'identifier' => [__('auth.login_otp_send_failed')],
            ]);
        }

        return response()->json([
            'message' => __('auth.login_otp_sent'),
            'channel' => $resolved['channel'],
        ]);
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $resolved = $this->resolution->resolve($validated['identifier']);

        if (! $resolved || ! $this->otpChallenge->verify($resolved['user'], $validated['otp'])) {
            $this->recordAuthFailure(
                'Invalid OTP',
                $validated['identifier'],
                $resolved ? $resolved['user']->user_id : null
            );
            throw ValidationException::withMessages([
                'otp' => [__('auth.login_otp_invalid')],
            ]);
        }

        $user = $resolved['user'];

        if ($resolved['channel'] === LoginResolutionService::CHANNEL_EMAIL && Schema::hasColumn('user', 'email_verified_at')) {
            $user->email_verified_at = now();
            $user->save();
        }

        if (! $user->registration_completed || ! $user->is_verified) {
            $this->recordAuthFailure('Account not verified', $validated['identifier'], $user->user_id);
            throw ValidationException::withMessages([
                'identifier' => [__('auth.account_not_verified')],
            ]);
        }

        if (Schema::hasColumn('user', 'application_status') && ! $this->applications->isApproved($user)) {
            $this->recordAuthFailure('Account not verified', $validated['identifier'], $user->user_id);
            throw ValidationException::withMessages([
                'identifier' => [__('auth.account_not_verified')],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'mobile';
        $church = \App\Tenancy\TenantContext::current();
        $abilities = (config('tenancy.enabled') && $church) ? ["church:{$church->slug}"] : ['*'];
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'ok']);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'second_name' => $user->second_name,
            'display_name' => $user->displayName(),
            'mobile_number' => $user->mobile_number,
            'communication_locale' => $user->communication_locale ?? null,
            'locale' => app()->getLocale(),
        ];
    }

    private function recordAuthFailure(string $reason, ?string $identifier, ?int $userId = null): void
    {
        try {
            $this->observability->record('auth', 'warning', 'API login failure: '.$reason, [
                'failure_reason' => $reason,
                'identifier' => $identifier,
                'user_id' => $userId,
                'channel' => 'api',
            ]);
        } catch (\Throwable) {
            //
        }
    }
}
