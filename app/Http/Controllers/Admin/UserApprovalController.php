<?php
// app/Http/Controllers/admin/UserApprovalController.php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    public function index()
    {
        $unverifiedUsers = User::where('is_approved', false)->get();

        return view('admin.users.index', compact('unverifiedUsers'));
    }

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->is_approved = true;
        $user->save();

        // Optionally, send email notification that account is approved

        return redirect()->route('admin.users.unverified')->with('success', 'User approved successfully.');
    }
}

?>