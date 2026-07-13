<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\RolesHubService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_name'      => 'required|string|max:30|unique:roles,role_name',
            'role_decription' => 'required|string|max:25',
        ]);

        Role::create($request->only('role_name', 'role_decription'));

        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'))
            ->with('success', 'تم إضافة الدور بنجاح');
    }

    public function destroy(string $id)
    {
        Role::findOrFail($id)->delete();
        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'))
            ->with('success', 'تم حذف الدور بنجاح');
    }
}
