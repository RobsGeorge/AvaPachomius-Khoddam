<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserSystemRole;
use App\Models\RoleAssignmentEmailTemplate;
use App\Services\PendingRegistrationService;
use App\Services\RoleAssignmentMailService;
use App\Services\RolesHubService;
use Illuminate\Http\Request;

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

        $visibleSections = $this->hub->visibleSections($user);
        $section = $request->query('section');
        if (! in_array($section, $visibleSections, true)) {
            $section = $visibleSections[0] ?? 'course';
        }

        $course = $this->hub->resolveCourse($user, $request->query('course'));
        $manageableCourses = $this->hub->manageableCourses($user);
        $service = $this->hub->resolveService($user, $request->query('service'));
        $manageableServices = $this->hub->manageableServices($user);

        $roles = collect();
        $assignments = collect();
        $canManageCourse = false;
        $canAssignCourse = false;

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
            $canCrossAddService = app(\App\Policies\RolePermissionPolicy::class)
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
                $serviceMembers = \App\Models\UserServiceRole::where('service_id', $service->service_id)
                    ->with(['user', 'role'])
                    ->orderByDesc('is_primary')
                    ->orderBy('user_id')
                    ->get();
                $serviceAssignUsers = User::orderBy('first_name')->orderBy('second_name')->get();
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
            }
        }

        $allAssignments = collect();
        $accountStatuses = collect();
        $users = collect();
        $rolesByCourse = collect();
        $legacyRoles = collect();
        $otherCourses = collect();

        if ($this->hub->canViewAllAssignments($user)) {
            $allAssignments = UserCourseRole::with(['user', 'course', 'role'])
                ->orderByDesc('course_id')
                ->get();
            $accountStatuses = $allAssignments->mapWithKeys(function (UserCourseRole $assignment) {
                $status = $assignment->user
                    ? PendingRegistrationService::accountStatus($assignment->user)
                    : PendingRegistrationService::unknownAccountStatus();

                return [$assignment->user_course_role_id => $status];
            });
            $users = User::orderBy('first_name')->orderBy('second_name')->get();
            $rolesByCourse = Role::assignableToCourses()
                ->with('course')
                ->orderBy('role_name')
                ->get()
                ->groupBy('course_id');
            $legacyRoles = Role::legacyGlobals()->orderBy('role_name')->get();
        }

        if ($course && $canManageCourse) {
            $otherCourses = Course::where('course_id', '!=', $course->course_id)
                ->orderBy('title')
                ->get();
        }

        $templates = collect();
        $templateGroups = collect();
        $systemRoles = collect();
        $systemGroups = collect();
        $systemAssignments = collect();
        $visibilityGroups = collect();

        if ($this->hub->canManageTemplates($user)) {
            $templates = Role::whereNull('course_id')
                ->where('is_template', true)
                ->with('permissions')
                ->orderBy('role_name')
                ->get();
            $templateGroups = PermissionGroup::with('permissions')
                ->orderBy('sort_order')
                ->get();
        }

        if ($this->hub->canManageSystemRoles($user)) {
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
        }

        if ($this->hub->canManageGroupVisibility($user)) {
            $visibilityGroups = PermissionGroup::with('visibility')
                ->orderBy('sort_order')
                ->get();
        }

        $assignUsers = $canAssignCourse || $canManageCourse
            ? User::orderBy('first_name')->orderBy('second_name')->get()
            : collect();

        $emailTemplates = collect();
        if ($this->hub->canManageEmailTemplates($user)) {
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
            'manageableServices',
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
            'canManageCourse',
            'canAssignCourse',
            'allAssignments',
            'accountStatuses',
            'users',
            'rolesByCourse',
            'legacyRoles',
            'otherCourses',
            'templates',
            'templateGroups',
            'systemRoles',
            'systemGroups',
            'systemAssignments',
            'visibilityGroups',
            'assignUsers',
            'emailTemplates',
        ));
    }

    public function updateEmailTemplates(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->hub->canManageEmailTemplates($user), 403);

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
}
