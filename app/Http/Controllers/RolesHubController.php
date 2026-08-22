<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\RoleAssignmentEmailTemplate;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Models\UserSystemRole;
use App\Policies\RolePermissionPolicy;
use App\Services\PendingRegistrationService;
use App\Services\RoleAssignmentMailService;
use App\Services\RolesHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RolesHubController extends Controller
{
    public function __construct(
        private RolesHubService $hub,
        private RoleAssignmentMailService $roleMail,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->hub->canAccess($user), 403);

        $service = $this->hub->resolveService($user, $request->query('service'), $request->query('course'));
        $visibleSections = $this->hub->visibleSections($user, $service);
        $section = $request->query('section');
        if (! in_array($section, $visibleSections, true)) {
            $section = $visibleSections[0] ?? null;
        }

        $manageableCourses = $this->hub->manageableCourses($user, $service);
        $course = $this->hub->resolveCourse($user, $request->query('course'), $service);
        $systemWide = $this->hub->isSystemWideMode($user, $service);

        $roles = collect();
        $assignments = collect();
        $accountStatuses = collect();
        $canManageCourse = false;
        $canAssignCourse = false;
        $otherCourses = collect();
        $assignUsers = collect();

        $serviceRoles = collect();
        $serviceMembers = collect();
        $servicePermissionGroups = collect();
        $canManageService = false;
        $canAssignService = false;
        $canCrossAddService = false;
        $serviceAssignUsers = collect();
        $crossCandidateUsers = collect();

        if ($service) {
            $canManageService = $this->hub->canManageService($user, $service);
            $canAssignService = $this->hub->canAssignInService($user, $service);
            $canCrossAddService = app(RolePermissionPolicy::class)
                ->addCrossServiceMember($user, $service);

            if ($canManageService) {
                $serviceRoles = Role::forService($service->service_id)
                    ->withCount('userServiceRoles')
                    ->with('permissions')
                    ->orderBy('role_name')
                    ->get();
                $servicePermissionGroups = PermissionGroup::query()
                    ->whereIn('scope', ['service', 'both', 'system'])
                    ->with('permissions')
                    ->orderBy('sort_order')
                    ->get();
            }

            if ($canManageService || $canAssignService) {
                $serviceMembers = UserServiceRole::where('service_id', $service->service_id)
                    ->with(['user', 'role'])
                    ->orderByDesc('is_primary')
                    ->orderBy('user_id')
                    ->get();
                $already = $serviceMembers->pluck('user_id');
                $serviceAssignUsers = User::query()
                    ->when($already->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $already))
                    ->orderBy('first_name')
                    ->orderBy('second_name')
                    ->get();
            }

            if ($canCrossAddService) {
                $already = $serviceMembers->pluck('user_id');
                $crossCandidateUsers = User::query()
                    ->whereHas('userServiceRoles')
                    ->when($already->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $already))
                    ->orderBy('first_name')
                    ->orderBy('second_name')
                    ->get();
            }
        }

        if ($course) {
            $canManageCourse = $this->hub->canManageCourse($user, $course);
            $canAssignCourse = $this->hub->canAssignInCourse($user, $course);

            if ($canManageCourse) {
                $roles = Role::where('course_id', $course->course_id)
                    ->withCount('userCourseRoles')
                    ->orderBy('role_name')
                    ->get();
            }

            if ($canManageCourse || $canAssignCourse) {
                $assignments = UserCourseRole::where('course_id', $course->course_id)
                    ->with(['user', 'role'])
                    ->orderBy('user_id')
                    ->get();
                $accountStatuses = $assignments->mapWithKeys(function (UserCourseRole $assignment) {
                    $status = $assignment->user
                        ? PendingRegistrationService::accountStatus($assignment->user)
                        : PendingRegistrationService::unknownAccountStatus();

                    return [$assignment->user_course_role_id => $status];
                });
                $assignUsers = $this->assignableUsersForCourse($course, $service);
            }

            if ($canManageCourse) {
                $scopeServiceId = $service?->service_id ?? $course->service_id;
                $otherCourses = Course::where('course_id', '!=', $course->course_id)
                    ->when($scopeServiceId, fn ($q) => $q->where('service_id', $scopeServiceId))
                    ->orderBy('title')
                    ->get();
            }
        }

        $templates = collect();
        $templateGroups = collect();
        $systemRoles = collect();
        $systemGroups = collect();
        $systemAssignments = collect();
        $visibilityGroups = collect();
        $emailTemplates = collect();
        $users = collect();

        if ($systemWide && $this->hub->canManageTemplates($user)) {
            $templates = Role::whereNull('course_id')
                ->where('is_template', true)
                ->with('permissions')
                ->orderBy('role_name')
                ->get();
            $templateGroups = PermissionGroup::with('permissions')
                ->orderBy('sort_order')
                ->get();
        }

        if ($systemWide && $this->hub->canManageSystemRoles($user)) {
            $systemRoles = Role::whereNull('course_id')
                ->where('is_template', false)
                ->where('is_system', true)
                ->with('permissions')
                ->orderBy('role_name')
                ->get();
            $systemGroups = PermissionGroup::whereIn('scope', ['system', 'both'])
                ->with('permissions')
                ->orderBy('sort_order')
                ->get();
            $systemAssignments = UserSystemRole::with(['user', 'role'])->get();
            $users = User::orderBy('first_name')->orderBy('second_name')->get();
        }

        if ($systemWide && $this->hub->canManageGroupVisibility($user)) {
            $visibilityGroups = PermissionGroup::with('visibility')
                ->orderBy('sort_order')
                ->get();
        }

        if ($systemWide && $this->hub->canManageEmailTemplates($user)) {
            $this->roleMail->ensureDefaults();
            $emailTemplates = RoleAssignmentEmailTemplate::query()
                ->orderBy('template_key')
                ->orderBy('locale')
                ->get()
                ->groupBy('template_key');
        }

        return view('roles-hub.index', compact(
            'user',
            'section',
            'visibleSections',
            'course',
            'manageableCourses',
            'service',
            'systemWide',
            'serviceRoles',
            'serviceMembers',
            'servicePermissionGroups',
            'canManageService',
            'canAssignService',
            'canCrossAddService',
            'serviceAssignUsers',
            'crossCandidateUsers',
            'roles',
            'assignments',
            'accountStatuses',
            'canManageCourse',
            'canAssignCourse',
            'otherCourses',
            'templates',
            'templateGroups',
            'systemRoles',
            'systemGroups',
            'systemAssignments',
            'visibilityGroups',
            'assignUsers',
            'emailTemplates',
            'users',
        ));
    }

    public function updateEmailTemplates(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->hub->canManageEmailTemplates($user), 403);
        abort_unless($this->hub->isSystemWideMode($user, $this->hub->resolveService($user, null)), 403);

        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:255'],
            'templates.*.body_html' => ['required', 'string'],
        ]);

        foreach ($validated['templates'] as $id => $payload) {
            RoleAssignmentEmailTemplate::query()
                ->whereKey($id)
                ->update([
                    'subject' => $payload['subject'],
                    'body_html' => $payload['body_html'],
                ]);
        }

        return redirect($this->hub->hubUrl(null, 'email-templates'))
            ->with('success', __('rbac.email_templates_saved'));
    }

    /** @return Collection<int, User> */
    private function assignableUsersForCourse(Course $course, ?ChurchService $service): Collection
    {
        $serviceId = $service?->service_id ?? $course->service_id;
        if (! $serviceId) {
            return collect();
        }

        return User::query()
            ->whereHas('userServiceRoles', fn ($q) => $q->where('service_id', $serviceId))
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }
}
