<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Course;
use App\Models\UserCourseRole;

class SuperAdminController extends Controller
{
    public function index()
    {
        $assignments = UserCourseRole::with(['user', 'course', 'role'])->get();
        $users        = User::orderBy('first_name')->get();
        $courses      = Course::orderBy('title')->get();
        $roles        = Role::all();

        return view('superadmin.index', compact('assignments', 'users', 'courses', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:user,user_id',
            'course_id' => 'required|exists:course,course_id',
            'role_id'   => 'required|exists:roles,role_id',
        ]);

        $exists = UserCourseRole::where('user_id', $request->user_id)
            ->where('course_id', $request->course_id)
            ->where('role_id', $request->role_id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['duplicate' => 'هذا المستخدم لديه هذا الدور في هذه الدورة بالفعل.'])
                ->withInput();
        }

        UserCourseRole::create($request->only('user_id', 'course_id', 'role_id'));

        return redirect()->route('superadmin.index')->with('success', 'تم تعيين الدور بنجاح');
    }

    public function destroy(string $id)
    {
        UserCourseRole::findOrFail($id)->delete();
        return redirect()->route('superadmin.index')->with('success', 'تم إلغاء تعيين الدور');
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'role_name'       => 'required|string|max:30|unique:roles,role_name',
            'role_decription' => 'required|string|max:25',
        ]);

        Role::create($request->only('role_name', 'role_decription'));

        return redirect()->route('superadmin.index')->with('success', 'تم إضافة الدور بنجاح');
    }

    public function destroyRole(string $id)
    {
        Role::findOrFail($id)->delete();
        return redirect()->route('superadmin.index')->with('success', 'تم حذف الدور بنجاح');
    }
}
