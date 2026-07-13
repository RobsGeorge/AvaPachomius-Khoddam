<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseAdminGroupVisibility;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserSystemRole;
use App\Services\RoleTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemRoleController extends Controller
{
    public function __construct(
        private RoleTemplateService $templates,
    ) {}

    public function templates()
    {
        $templates = Role::whereNull('course_id')
            ->where('is_template', true)
            ->with('permissions.group')
            ->orderBy('role_name')
            ->get();

        $groups = PermissionGroup::with('permissions')
            ->orderBy('sort_order')
            ->get();

        return view('superadmin.rbac.templates', compact('templates', 'groups'));
    }

    public function updateTemplate(Request $request, Role $role)
    {
        abort_unless($role->is_template && ! $role->course_id, 404);

        $data = $request->validate([
            'role_name' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,permission_id',
        ]);

        $role->update([
            'role_name' => $data['role_name'],
            'role_decription' => Str::limit($data['description'] ?? $data['role_name'], 25, ''),
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('success', __('rbac.template_updated'));
    }

    public function groupVisibility()
    {
        $groups = PermissionGroup::with('visibility')
            ->orderBy('sort_order')
            ->get();

        return view('superadmin.rbac.group-visibility', compact('groups'));
    }

    public function updateGroupVisibility(Request $request)
    {
        $data = $request->validate([
            'visible_groups' => 'array',
            'visible_groups.*' => 'integer|exists:permission_groups,permission_group_id',
        ]);

        $visible = collect($data['visible_groups'] ?? []);

        foreach (PermissionGroup::all() as $group) {
            CourseAdminGroupVisibility::updateOrCreate(
                ['permission_group_id' => $group->permission_group_id],
                [
                    'visible_to_course_admins' => $visible->contains($group->permission_group_id),
                    'set_by_user_id' => $request->user()->user_id,
                ]
            );
        }

        return back()->with('success', __('rbac.visibility_updated'));
    }

    public function systemRoles()
    {
        $roles = Role::whereNull('course_id')
            ->where('is_template', false)
            ->with('permissions')
            ->orderBy('role_name')
            ->get();

        $groups = PermissionGroup::whereIn('scope', ['system', 'both'])
            ->with('permissions')
            ->orderBy('sort_order')
            ->get();

        $assignments = UserSystemRole::with(['user', 'role'])->get();

        return view('superadmin.rbac.system-roles', compact('roles', 'groups', 'assignments'));
    }

    public function storeSystemRole(Request $request)
    {
        $data = $request->validate([
            'role_name' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,permission_id',
        ]);

        $slug = Str::slug($data['role_name']);

        $role = Role::create([
            'role_name' => $data['role_name'],
            'role_decription' => Str::limit($data['description'] ?? $data['role_name'], 25, ''),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'course_id' => null,
            'is_system' => true,
            'is_template' => false,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('success', __('rbac.system_role_created'));
    }

    public function assignSystemRole(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
            'role_id' => [
                'required',
                Rule::exists('roles', 'role_id')->where(fn ($q) => $q->whereNull('course_id')->where('is_template', false)),
            ],
        ]);

        UserSystemRole::firstOrCreate($data);

        return back()->with('success', __('rbac.system_role_assigned'));
    }

    public function destroySystemRoleAssignment(UserSystemRole $userSystemRole)
    {
        $userSystemRole->delete();

        return back()->with('success', __('rbac.system_role_unassigned'));
    }
}
