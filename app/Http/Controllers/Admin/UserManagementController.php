<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserManagementController extends Controller
{
    public function index()
    {
        $unverifiedUsers = User::where('is_verified', false)->get();
        return view('admin.users.index', compact('unverifiedUsers'));
    }

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->is_verified = true;
        $user->save();

        // Notify user by email or other means here if needed

        return redirect()->back()->with('success', 'User approved successfully.');
    }
}

?>
