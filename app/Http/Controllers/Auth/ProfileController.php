<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    // Show user profile with all basic info
    public function index()
    {
        $user = Auth::user(); // Ensure $user is an instance of the User model
        if (!$user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        $fullName = trim("{$user->first_name} {$user->second_name} {$user->third_name}");
        
        // URL that the QR code will encode, e.g. attendance mark route:
        $attendanceUrl = route('attendance.mark', ['user_id' => $user->user_id]);

        return view('profile.show', [
            'user' => $user,
            'attendanceUrl' => $attendanceUrl,
            'fullName' => $fullName,
        ]);
    }

    // Update user profile picture
    public function updatePicture(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|max:2048',
        ]);

        $user = Auth::user();

        // Delete old picture if it exists
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }
        

        // Store new picture
        $path = $request->file('profile_photo')->store('profile_photos', 'public');

        $user->profile_photo = $path;
        $user->save();

        return redirect()->route('profile.show')->with('success', 'تم تحديث صورة الملف الشخصي بنجاح.');
    }
}
?>