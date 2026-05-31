<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return view('roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_name'      => 'required|string|max:30|unique:roles,role_name',
            'role_decription' => 'required|string|max:25',
        ]);

        Role::create($request->only('role_name', 'role_decription'));

        return redirect()->route('roles.index')->with('success', 'تم إضافة الدور بنجاح');
    }

    public function destroy(string $id)
    {
        Role::findOrFail($id)->delete();
        return redirect()->route('roles.index')->with('success', 'تم حذف الدور بنجاح');
    }
}
