<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'second_name' => $user->second_name,
                'third_name' => $user->third_name,
                'display_name' => $user->displayName(),
                'mobile_number' => $user->mobile_number,
                'communication_locale' => $user->communication_locale ?? null,
                'is_verified' => (bool) $user->is_verified,
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'communication_locale' => ['nullable', 'in:ar,en'],
        ]);

        if (array_key_exists('communication_locale', $data)
            && \Illuminate\Support\Facades\Schema::hasColumn('user', 'communication_locale')) {
            $user->communication_locale = $data['communication_locale'];
            $user->save();
        }

        return $this->show($request);
    }
}
