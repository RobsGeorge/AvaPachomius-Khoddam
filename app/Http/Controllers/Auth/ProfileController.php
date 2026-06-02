<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
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

        return view('profile.show', compact('user', 'attendanceUrl', 'fullName'));
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
        $user->save();

        return redirect()->route('profile')->with('success', 'تم تحديث صورة الملف الشخصي بنجاح.');
    }
}
