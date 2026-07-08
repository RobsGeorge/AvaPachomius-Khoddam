<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfilePhotoGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(
        private ProfilePhotoGateService $photoGate
    ) {}

    public function index()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        $fullName = User::fullNameFromParts(
            $user->first_name ?? '',
            $user->second_name ?? '',
            $user->third_name ?? ''
        );

        $attendanceUrl = route('attendance.sessions', ['user_id' => $user->user_id], true);

        return view('profile.show', [
            'user' => $user,
            'attendanceUrl' => $attendanceUrl,
            'fullName' => $fullName,
            'photoGateBlocked' => $this->photoGate->isHardBlocked($user),
            'photoDeadline' => $this->photoGate->deadlineFor($user),
            'photoDaysRemaining' => $this->photoGate->daysRemaining($user),
            'photoPendingReview' => $this->photoGate->shouldShowPendingBanner($user),
            'photoRejected' => $this->photoGate->shouldShowRejectedBanner($user),
            'photoRejectionNote' => $user->profile_photo_rejection_note,
        ]);
    }

    public function updatePicture(Request $request)
    {
        $request->validate([
            'profile_photo' => 'image|max:2048',
        ]);

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('profile_photo')->store('profile_photos', 'public');

        $user->profile_photo = $path;
        $user->profile_photo_uploaded_at = now($this->photoGate->timezone());
        $user->profile_photo_status = User::PHOTO_STATUS_PENDING;
        $user->profile_photo_reviewed_at = null;
        $user->profile_photo_reviewed_by_user_id = null;
        $user->profile_photo_rejection_note = null;
        $user->save();

        return redirect()->route('profile')->with('success', __('pages.profile_photo_updated_pending'));
    }
}
