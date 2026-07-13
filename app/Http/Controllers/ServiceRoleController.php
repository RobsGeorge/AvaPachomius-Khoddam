<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\RolesHubService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceRoleController extends Controller
{
    public function __construct(
        private RolePermissionPolicy $policy,
        private RolesHubService $hub,
    ) {}

    public function store(Request $request, ChurchService $service)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->manageServiceRoles($actor, $service), 403);

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('service_id', $service->service_id)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,permission_id'],
        ]);

        $slug = $validated['slug'] ?: \Illuminate\Support\Str::slug($validated['role_name']);

        $role = Role::create([
            'role_name' => $validated['role_name'],
            'role_decription' => substr($validated['role_name'], 0, 25),
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'course_id' => null,
            'service_id' => $service->service_id,
            'is_system' => false,
            'is_template' => false,
        ]);

        $permissionIds = $validated['permission_ids'] ?? [];
        $allowed = Permission::query()
            ->whereIn('permission_id', $permissionIds)
            ->whereHas('group', fn ($q) => $q->whereIn('scope', ['service', 'both', 'system']))
            ->pluck('permission_id');

        $role->permissions()->sync($allowed);
        $service->bumpPermissionsVersion();

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.role_created'));
    }

    public function update(Request $request, ChurchService $service, Role $role)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->manageServiceRoles($actor, $service), 403);
        abort_unless((int) $role->service_id === (int) $service->service_id && $role->course_id === null, 404);

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,permission_id'],
        ]);

        $role->update([
            'role_name' => $validated['role_name'],
            'role_decription' => substr($validated['role_name'], 0, 25),
            'description' => $validated['description'] ?? null,
        ]);

        $permissionIds = $validated['permission_ids'] ?? [];
        $allowed = Permission::query()
            ->whereIn('permission_id', $permissionIds)
            ->whereHas('group', fn ($q) => $q->whereIn('scope', ['service', 'both', 'system']))
            ->pluck('permission_id');

        $role->permissions()->sync($allowed);
        $service->bumpPermissionsVersion();

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.role_updated'));
    }

    public function destroy(Request $request, ChurchService $service, Role $role)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->manageServiceRoles($actor, $service), 403);
        abort_unless((int) $role->service_id === (int) $service->service_id && $role->course_id === null, 404);

        if ($role->userServiceRoles()->exists()) {
            return back()->withErrors(['role' => __('service.role_in_use')]);
        }

        $role->permissions()->detach();
        $role->delete();
        $service->bumpPermissionsVersion();

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.role_deleted'));
    }
}
