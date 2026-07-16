<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.credentials_mismatch')],
            ]);
        }

        if (! $user->registration_completed || ! $user->is_verified) {
            throw ValidationException::withMessages([
                'email' => [__('auth.account_not_verified')],
            ]);
        }

        if (Schema::hasColumn('user', 'application_status') && ! $this->applications->isApproved($user)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.account_not_verified')],
            ]);
        }

        $deviceName = $credentials['device_name'] ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

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
}
