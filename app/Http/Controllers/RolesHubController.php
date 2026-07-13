<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserSystemRole;
use App\Services\PendingRegistrationService;
use App\Services\RolesHubService;
use Illuminate\Http\Request;

class RolesHubController extends Controller
{
    public function __construct(
        private RolesHubService $hub,
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

        $roles = collect();
        $assignments = collect();
        $canManageCourse = false;
        $canAssignCourse = false;

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

        return view('roles-hub.index', compact(
            'user',
            'section',
            'visibleSections',
            'course',
            'manageableCourses',
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
        ));
    }
}
