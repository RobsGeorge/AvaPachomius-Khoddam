<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Policies\RolePermissionPolicy;
use App\Services\CoursePermissionResolver;
use App\Services\CourseRoleAssignmentService;
use App\Services\RoleTemplateService;
use App\Services\RolesHubService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseRoleController extends Controller
{
    public function __construct(
        private RolePermissionPolicy $policy,
        private CoursePermissionResolver $resolver,
        private RoleTemplateService $templates,
        private StudentRosterService $roster,
        private RolesHubService $hub,
        private CourseRoleAssignmentService $assignments,
    ) {}

    public function index(Course $course)
    {
        return redirect($this->hub->hubUrl($course, 'course'));
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeManage($course);

        $data = $request->validate([
            'role_name' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:64',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['role_name']);
        $slug = $this->templates->uniqueSlugForCourse($course->course_id, $slug);

        $role = Role::create([
            'role_name' => $data['role_name'],
            'role_decription' => Str::limit($data['description'] ?? $data['role_name'], 25, ''),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'course_id' => $course->course_id,
            'is_system' => false,
            'is_template' => false,
        ]);

        $this->resolver->bumpCoursePermissionsVersion($course);

        return redirect()
            ->route('courses.roles.edit', [$course, $role])
            ->with('success', __('rbac.role_created'));
    }

    public function edit(Course $course, Role $role)
    {
        $this->authorizeManage($course);
        abort_unless($role->course_id === $course->course_id, 404);

        $groups = $this->policy->visibleGroupsForCourseAdmin();
        $assignedIds = $role->permissions()->pluck('permissions.permission_id')->all();

        return view('course-roles.edit', compact('course', 'role', 'groups', 'assignedIds'));
    }

    public function update(Request $request, Course $course, Role $role)
    {
        $this->authorizeManage($course);
        abort_unless($role->course_id === $course->course_id, 404);

        $data = $request->validate([
            'role_name' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,permission_id',
        ]);

        abort_unless(
            $this->policy->updateRolePermissions($request->user(), $role, $data['permissions'] ?? []),
            403
        );

        $role->update([
            'role_name' => $data['role_name'],
            'role_decription' => Str::limit($data['description'] ?? $data['role_name'], 25, ''),
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
        $this->resolver->bumpCoursePermissionsVersion($course);

        return redirect($this->hub->hubUrl($course, 'course'))
            ->with('success', __('rbac.role_updated'));
    }

    public function destroy(Course $course, Role $role)
    {
        $this->authorizeManage($course);
        abort_unless($role->course_id === $course->course_id, 404);
        abort_unless($this->policy->deleteRole(request()->user(), $role), 422);

        $role->permissions()->detach();
        $role->delete();
        $this->resolver->bumpCoursePermissionsVersion($course);

        return redirect($this->hub->hubUrl($course, 'course'))
            ->with('success', __('rbac.role_deleted'));
    }

    public function storeAssignment(Request $request, Course $course)
    {
        abort_unless($this->policy->assignUsers($request->user(), $course), 403);

        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
            'role_id' => [
                'required',
                Rule::exists('roles', 'role_id')->where(fn ($q) => $q->where('course_id', $course->course_id)),
            ],
        ]);

        $user = User::findOrFail($data['user_id']);

        $existing = UserCourseRole::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->first();

        if ($existing && (int) $existing->role_id === (int) $data['role_id']) {
            return redirect($this->hub->hubUrl($course, 'course'))
                ->with('error', __('pages.duplicate_role_assignment'));
        }

        $this->assignments->assignOrUpdate($user, $course->course_id, (int) $data['role_id']);

        $this->resolver->bumpCoursePermissionsVersion($course);

        return redirect($this->hub->hubUrl($course, 'course'))
            ->with('success', __('rbac.user_assigned'));
    }

    public function destroyAssignment(Course $course, UserCourseRole $userCourseRole)
    {
        abort_unless($userCourseRole->course_id === $course->course_id, 404);
        abort_unless($this->policy->assignUsers(request()->user(), $course), 403);

        $userCourseRole->delete();
        $this->resolver->bumpCoursePermissionsVersion($course);

        return redirect($this->hub->hubUrl($course, 'course'))
            ->with('success', __('rbac.user_unassigned'));
    }

    public function copyFrom(Request $request, Course $course)
    {
        $this->authorizeManage($course);

        $data = $request->validate([
            'source_course_id' => 'nullable|exists:course,course_id',
            'use_templates' => 'boolean',
        ]);

        if ($request->boolean('use_templates')) {
            $this->templates->cloneTemplatesIntoCourse($course);
        } elseif (! empty($data['source_course_id'])) {
            $source = Course::findOrFail($data['source_course_id']);
            $this->templates->copyRolesFromCourse($course, $source);
        }

        return redirect($this->hub->hubUrl($course, 'course'))
            ->with('success', __('rbac.roles_copied'));
    }

    private function authorizeManage(Course $course): void
    {
        $user = request()->user();
        abort_unless(
            ($user->is_superadmin ?? false) || $this->policy->manageCourseRoles($user, $course),
            403
        );
    }
}
