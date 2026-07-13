<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\RolesHubService;
use App\Services\ServiceRoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceMemberController extends Controller
{
    public function __construct(
        private ServiceRoleAssignmentService $assignments,
        private RolePermissionPolicy $policy,
        private RolesHubService $hub,
    ) {}

    public function store(Request $request, ChurchService $service)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->assignServiceUsers($actor, $service), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'role_id' => [
                'required',
                Rule::exists('roles', 'role_id')->where(
                    fn ($q) => $q
                        ->where('service_id', $service->service_id)
                        ->whereNull('course_id')
                        ->where('is_template', false)
                ),
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        $this->assignments->assign(
            $user,
            $service,
            $role,
            asPrimary: (bool) ($validated['is_primary'] ?? false),
            allowCrossService: false,
        );

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.member_added'));
    }

    public function cross(Request $request, ChurchService $service)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->addCrossServiceMember($actor, $service), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'role_id' => [
                'nullable',
                Rule::exists('roles', 'role_id')->where(
                    fn ($q) => $q
                        ->where('service_id', $service->service_id)
                        ->whereNull('course_id')
                        ->where('is_template', false)
                ),
            ],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = ! empty($validated['role_id'])
            ? Role::findOrFail($validated['role_id'])
            : null;

        $this->assignments->addCrossService($user, $service, $role);

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.member_cross_added'));
    }

    public function destroy(Request $request, ChurchService $service, User $user)
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->policy->removeServiceMember($actor, $service), 403);

        $this->assignments->remove($user, $service);

        return redirect()
            ->to($this->hub->hubUrl(section: 'service', service: $service))
            ->with('success', __('service.member_removed'));
    }
}
