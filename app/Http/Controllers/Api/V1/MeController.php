<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->serialize($user)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'communication_locale' => ['nullable', 'in:ar,en'],
            'locale' => ['nullable', 'in:ar,en'],
        ]);

        $locale = $data['communication_locale'] ?? $data['locale'] ?? null;
        if ($locale !== null && Schema::hasColumn('user', 'communication_locale')) {
            $user->communication_locale = $locale;
            $user->save();
        }

        return $this->show($request);
    }

    public function updatePicture(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'profile_photo' => ['required', 'image', 'max:2048'],
        ]);

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('profile_photo')->store('profile_photos', 'public');

        $user->profile_photo = $path;
        $user->profile_photo_uploaded_at = now();
        $user->profile_photo_status = User::PHOTO_STATUS_PENDING;
        $user->profile_photo_reviewed_at = null;
        $user->profile_photo_reviewed_by_user_id = null;
        $user->profile_photo_rejection_note = null;
        $user->save();

        return response()->json([
            'data' => $this->serialize($user->fresh()),
            'message' => __('pages.profile_photo_updated_pending'),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(User $user): array
    {
        return [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'second_name' => $user->second_name,
            'third_name' => $user->third_name,
            'display_name' => $user->displayName(),
            'mobile_number' => $user->mobile_number,
            'communication_locale' => $user->communication_locale ?? null,
            'is_verified' => (bool) $user->is_verified,
            'profile_photo_url' => $user->profile_photo
                ? Storage::disk('public')->url($user->profile_photo)
                : null,
            'profile_photo_status' => $user->profile_photo_status,
            'profile_photo_rejection_note' => $user->profile_photo_rejection_note,
            'application_status' => $user->application_status ?? null,
        ];
    }
}
