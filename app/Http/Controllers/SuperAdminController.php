<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Role;
use App\Models\StructureTemplate;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\AuditLogService;
use App\Services\CourseRoleAssignmentService;
use App\Services\EventAdminRoleService;
use App\Services\ForceLogoutService;
use App\Services\ImpersonationService;
use App\Services\PlatformAccessService;
use App\Services\RolePreviewService;
use App\Services\RoleTemplateService;
use App\Services\RolesHubService;
use App\Support\NavigationHub;
use App\Support\Structure\ProgressionPolicy;
use App\Support\SuperadminWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperAdminController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $sections = NavigationHub::superadminSections($user);

        return view('superadmin.index', compact('sections'));
    }

    public function courses()
    {
        $requiresChurch = SuperadminWorkspace::requiresExplicitChurchScope();
        $hasCourseChurch = Schema::hasColumn('course', 'church_id');
        $hasServiceChurch = ChurchService::tableReady() && Schema::hasColumn('service', 'church_id');
        $showChurchColumn = Schema::hasTable('church') && ($hasCourseChurch || $hasServiceChurch);

        $courseWith = ['service'];
        if ($hasServiceChurch) {
            $courseWith[] = 'service.church';
        }
        if ($hasCourseChurch && Schema::hasTable('church')) {
            $courseWith[] = 'church';
        }

        $courseQuery = Course::query()
            ->with($courseWith)
            ->when($requiresChurch, fn ($q) => $q->withoutTenancy());

        if ($hasCourseChurch) {
            $courseQuery->orderBy('church_id');
        }
        if (Schema::hasColumn('course', 'service_id')) {
            $courseQuery->orderBy('service_id');
        }

        $courses = $courseQuery
            ->orderBy('year', 'desc')
            ->orderBy('title')
            ->get();

        $groupedCourses = ($hasCourseChurch || $hasServiceChurch)
            ? $courses->groupBy(function (Course $course) use ($hasCourseChurch) {
                if ($hasCourseChurch && $course->church_id) {
                    return (int) $course->church_id;
                }

                return (int) ($course->service?->church_id ?? 0);
            })
            : collect([0 => $courses]);

        $services = ChurchService::tableReady()
            ? tap(
                ChurchService::query()
                    ->when($hasServiceChurch, fn ($q) => $q->with('church'))
                    ->when($requiresChurch, fn ($q) => $q->withoutTenancy())
                    ->where('status', ChurchService::STATUS_ACTIVE),
                function ($query) use ($hasServiceChurch) {
                    if ($hasServiceChurch) {
                        $query->orderBy('church_id');
                    }
                    $query->orderBy('title');
                }
            )->get()
            : collect();

        // Services management panel: all statuses, with counts, grouped by church.
        $manageServices = ChurchService::tableReady()
            ? tap(
                ChurchService::query()
                    ->when($hasServiceChurch, fn ($q) => $q->with('church'))
                    ->withCount(['courses', 'userServiceRoles'])
                    ->when($requiresChurch, fn ($q) => $q->withoutTenancy()),
                function ($query) use ($hasServiceChurch) {
                    if ($hasServiceChurch) {
                        $query->orderBy('church_id');
                    }
                    $query->orderBy('title');
                }
            )->get()
            : collect();

        $groupedServices = $manageServices->groupBy(
            fn (ChurchService $service) => (int) ($service->church_id ?? 0)
        );

        $churches = $requiresChurch
            ? Church::query()->orderBy('name')->get(['church_id', 'name', 'slug'])
            : collect();

        $supportsLocalizedFields = Schema::hasColumn('course', 'title_ar')
            && Schema::hasColumn('course', 'title_en');

        $structureTemplates = StructureTemplate::query()->orderBy('name_en')->get();
        $progressionPolicies = ProgressionPolicy::all();

        return view('superadmin.courses', compact(
            'courses',
            'services',
            'groupedCourses',
            'groupedServices',
            'churches',
            'requiresChurch',
            'showChurchColumn',
            'supportsLocalizedFields',
            'structureTemplates',
            'progressionPolicies',
        ));
    }

    public function courseRoles()
    {
        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'));
    }

    public function security()
    {
        $users = User::with('roles')->orderBy('first_name')->get();
        $courses = Course::orderBy('year', 'desc')->orderBy('title')->get();
        $rolesByCourse = Role::assignableToCourses()
            ->with('course')
            ->orderBy('role_name')
            ->get()
            ->groupBy('course_id');
        $systemRoles = Role::query()
            ->whereNull('course_id')
            ->where('is_template', false)
            ->where('is_system', true)
            ->orderBy('role_name')
            ->get();

        return view('superadmin.security', compact('users', 'courses', 'rolesByCourse', 'systemRoles'));
    }

    public function eventAdmins()
    {
        $users = User::orderBy('first_name')->get();
        $eventAdmins = \App\Models\EventAdmin::with('user')->get();

        return view('superadmin.event-admins', compact('users', 'eventAdmins'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:user,user_id',
            'course_id' => 'required|exists:course,course_id',
            'role_id'   => [
                'required',
                Rule::exists('roles', 'role_id')->where(
                    fn ($query) => $query
                        ->where('course_id', $request->input('course_id'))
                        ->where('is_template', false)
                ),
            ],
        ]);

        $exists = UserCourseRole::where('user_id', $request->user_id)
            ->where('course_id', $request->course_id)
            ->where('role_id', $request->role_id)
            ->exists();

        if ($exists) {
            return back()
                ->with('error', __('pages.duplicate_role_assignment'))
                ->withInput();
        }

        $user = User::findOrFail($request->user_id);
        app(CourseRoleAssignmentService::class)->assignOrUpdate(
            $user,
            (int) $request->course_id,
            (int) $request->role_id
        );

        $course = Course::find($request->course_id);
        if ($course) {
            app(\App\Services\CoursePermissionResolver::class)->bumpCoursePermissionsVersion($course);
        }

        return redirect(app(RolesHubService::class)->hubUrl($course, 'assignments'))
            ->with('success', __('pages.role_assigned'));
    }

    public function destroy(string $id)
    {
        $assignment = UserCourseRole::findOrFail($id);
        $course = Course::find($assignment->course_id);
        $assignment->delete();

        if ($course) {
            app(\App\Services\CoursePermissionResolver::class)->bumpCoursePermissionsVersion($course);
        }

        return redirect(app(RolesHubService::class)->hubUrl($course, 'assignments'))
            ->with('success', __('pages.role_unassigned'));
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'role_name'       => 'required|string|max:30|unique:roles,role_name',
            'role_decription' => 'required|string|max:25',
        ]);

        Role::create($request->only('role_name', 'role_decription'));

        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'))
            ->with('success', __('pages.role_created'));
    }

    public function destroyRole(string $id)
    {
        Role::findOrFail($id)->delete();

        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'))
            ->with('success', __('pages.role_deleted'));
    }

    public function storeCourse(Request $request)
    {
        $rules = [
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'year'        => 'required|integer|min:2000|max:2100',
            'default_session_start_time' => 'required|date_format:H:i',
            'clone_templates' => 'boolean',
            'inherit_from_course_id' => 'nullable|exists:course,course_id',
        ];

        if (ChurchService::tableReady()) {
            $rules['service_id'] = 'required|exists:service,service_id';
        }

        if (SuperadminWorkspace::requiresExplicitChurchScope()) {
            $rules['church_id'] = 'required|integer|exists:church,church_id';
        }

        $request->validate($rules);

        if (ChurchService::tableReady() && SuperadminWorkspace::requiresExplicitChurchScope()) {
            $service = ChurchService::query()->withoutTenancy()->findOrFail((int) $request->input('service_id'));
            if ((int) $service->church_id !== (int) $request->input('church_id')) {
                throw ValidationException::withMessages([
                    'service_id' => [__('pages.course_service_church_mismatch')],
                ]);
            }
        }

        $payload = [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'year' => $request->input('year'),
            'default_session_start_time' => $request->input('default_session_start_time').':00',
        ];

        if (ChurchService::tableReady()) {
            $payload['service_id'] = (int) $request->input('service_id');
        }

        $course = Course::create($payload);

        if (SuperadminWorkspace::requiresExplicitChurchScope() && Schema::hasColumn('course', 'church_id')) {
            $course->church_id = (int) $request->input('church_id');
            $course->save();
        }

        $templates = app(RoleTemplateService::class);
        if ($request->boolean('clone_templates', true)) {
            if ($request->filled('inherit_from_course_id')) {
                $source = Course::findOrFail($request->inherit_from_course_id);
                $templates->copyRolesFromCourse($course, $source);
            } else {
                $templates->cloneTemplatesIntoCourse($course);
            }
        }

        return redirect()->route('superadmin.courses')->with('success', __('pages.course_created'));
    }

    public function updateCourse(Request $request, string $id)
    {
        $course = Course::query()
            ->when(SuperadminWorkspace::requiresExplicitChurchScope(), fn ($q) => $q->withoutTenancy())
            ->findOrFail($id);

        $rules = [
            'title' => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'year' => 'required|integer|min:2000|max:2100',
            'default_session_start_time' => 'required|date_format:H:i',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description_ar' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
        ];

        if (ChurchService::tableReady()) {
            $rules['service_id'] = 'required|exists:service,service_id';
        }

        if (SuperadminWorkspace::requiresExplicitChurchScope()) {
            $rules['church_id'] = 'required|integer|exists:church,church_id';
        }

        $request->validate($rules);

        if (ChurchService::tableReady() && SuperadminWorkspace::requiresExplicitChurchScope()) {
            $service = ChurchService::query()->withoutTenancy()->findOrFail((int) $request->input('service_id'));
            if ((int) $service->church_id !== (int) $request->input('church_id')) {
                throw ValidationException::withMessages([
                    'service_id' => [__('pages.course_service_church_mismatch')],
                ]);
            }
        }

        $payload = [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'year' => $request->input('year'),
            'default_session_start_time' => $request->input('default_session_start_time').':00',
        ];

        if (Schema::hasColumn('course', 'title_ar')) {
            $payload['title_ar'] = $request->input('title_ar');
            $payload['title_en'] = $request->input('title_en');
            $payload['description_ar'] = $request->input('description_ar');
            $payload['description_en'] = $request->input('description_en');
        }

        if (ChurchService::tableReady()) {
            $payload['service_id'] = (int) $request->input('service_id');
        }

        $course->update($payload);

        if (SuperadminWorkspace::requiresExplicitChurchScope() && Schema::hasColumn('course', 'church_id')) {
            $course->church_id = (int) $request->input('church_id');
            $course->save();
        }

        return redirect()
            ->route('superadmin.courses')
            ->with('success', __('pages.course_updated'));
    }

    public function destroyCourse(string $id)
    {
        $course = Course::findOrFail($id);

        if (UserCourseRole::where('course_id', $course->course_id)->exists()) {
            return back()->withErrors(['course' => __('pages.course_delete_has_assignments')]);
        }

        if ($course->sessions()->exists()) {
            return back()->withErrors(['course' => __('pages.course_delete_has_sessions')]);
        }

        $course->delete();

        return redirect()->route('superadmin.courses')->with('success', __('pages.course_deleted'));
    }

    public function storeEventAdmin(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
        ]);

        $user = User::findOrFail($data['user_id']);

        \App\Models\EventAdmin::firstOrCreate(
            ['user_id' => $user->user_id],
            ['assigned_by_id' => auth()->id(), 'assigned_at' => now()]
        );

        app(EventAdminRoleService::class)->grant($user);

        \App\Services\EventAuditService::log('admin.assign', 'success', [
            'target_user_id' => $data['user_id'],
        ]);

        return redirect()->route('superadmin.event-admins')->with('success', __('events.event_admin_assigned'));
    }

    public function destroyEventAdmin(string $userId)
    {
        $user = User::find($userId);

        \App\Models\EventAdmin::where('user_id', $userId)->delete();

        if ($user) {
            app(EventAdminRoleService::class)->revoke($user);
        }

        \App\Services\EventAuditService::log('admin.unassign', 'success', [
            'target_user_id' => (int) $userId,
        ]);

        return redirect()->route('superadmin.event-admins')->with('success', __('events.event_admin_removed'));
    }

    public function flushAllSessions(Request $request)
    {
        $result = ForceLogoutService::logoutAllUsers($request->session()->getId());

        AuditLogService::recordEvent('platform.sessions_flush_all', [
            'sessions_cleared' => $result['sessions_cleared'],
            'remember_tokens_cleared' => $result['remember_tokens_cleared'],
            'driver' => $result['driver'],
        ]);

        return redirect()
            ->route('superadmin.security')
            ->with('success', __('pages.force_logout_success', [
                'sessions' => $result['sessions_cleared'],
                'tokens'   => $result['remember_tokens_cleared'],
                'driver'   => $result['driver'],
            ]));
    }

    public function flushSelectedUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:user,user_id'],
        ]);

        $result = ForceLogoutService::logoutUsers(
            $validated['user_ids'],
            $request->session()->getId()
        );

        AuditLogService::recordEvent('platform.users_cache_flush', [
            'target_user_ids' => $validated['user_ids'],
            'sessions_cleared' => $result['sessions_cleared'],
            'remember_tokens_cleared' => $result['remember_tokens_cleared'],
            'users_targeted' => $result['users_targeted'],
            'driver' => $result['driver'],
        ]);

        return redirect()
            ->route('superadmin.security')
            ->with('success', __('pages.flush_users_success', [
                'users' => $result['users_targeted'],
                'sessions' => $result['sessions_cleared'],
                'tokens' => $result['remember_tokens_cleared'],
                'driver' => $result['driver'],
            ]));
    }

    public function impersonate(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:user,user_id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        ImpersonationService::start($request->user(), $user, $request);

        return redirect(ImpersonationService::landingUrlFor($user))
            ->with('success', __('pages.impersonate_started', [
                'name' => User::fullNameFromParts($user->first_name, $user->second_name, $user->third_name),
            ]));
    }

    public function stopImpersonating(Request $request)
    {
        ImpersonationService::stop($request);

        $redirect = config('tenancy.enabled')
            ? \App\Support\ChurchHost::consoleUrl('/superadmin/security')
            : route('superadmin.security');

        return redirect($redirect)
            ->with('success', __('pages.impersonate_stopped'));
    }

    public function previewRole(Request $request)
    {
        $superadmin = $request->user();
        abort_unless($superadmin?->is_superadmin, 403);

        $isGeneral = $request->boolean('general_role');

        if ($isGeneral) {
            $validated = $request->validate([
                'general_role' => ['sometimes', 'boolean'],
                'role_id' => [
                    'required',
                    Rule::exists('roles', 'role_id')->where(
                        fn ($query) => $query
                            ->whereNull('course_id')
                            ->where('is_template', false)
                            ->where('is_system', true)
                    ),
                ],
            ]);

            $role = Role::findOrFail($validated['role_id']);
            RolePreviewService::startGeneralRole($superadmin, $role, $request);
        } else {
            $validated = $request->validate([
                'course_id' => ['required', 'integer', 'exists:course,course_id'],
                'role_id' => [
                    'required',
                    Rule::exists('roles', 'role_id')->where(
                        fn ($query) => $query
                            ->where('course_id', $request->input('course_id'))
                            ->where('is_template', false)
                    ),
                ],
            ]);

            $course = Course::findOrFail($validated['course_id']);
            $role = Role::findOrFail($validated['role_id']);
            RolePreviewService::startCourseRole($superadmin, $course, $role, $request);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('pages.role_preview_started', [
                'label' => RolePreviewService::label(),
            ]));
    }

    public function stopRolePreview(Request $request)
    {
        $wasChurchAdmin = RolePreviewService::isChurchAdminMode();
        RolePreviewService::stop($request);

        $redirect = ($wasChurchAdmin && config('tenancy.enabled'))
            ? \App\Support\ChurchHost::consoleUrl('/superadmin/churches')
            : route('superadmin.security');

        return redirect($redirect)
            ->with('success', __('pages.role_preview_stopped'));
    }

    public function stopPlatformAccess(Request $request)
    {
        abort_unless($request->user()?->is_superadmin, 403);

        PlatformAccessService::stop($request);

        return redirect(PlatformAccessService::consoleExitUrl())
            ->with('success', __('workspace.platform_access_stopped'));
    }
}
