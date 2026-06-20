<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\ForceLogoutService;
use App\Services\ImpersonationService;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function index()
    {
        $assignments = UserCourseRole::with(['user', 'course', 'role'])->get();
        $users       = User::with('roles')->orderBy('first_name')->get();
        $courses     = Course::orderBy('year', 'desc')->orderBy('title')->get();
        $roles       = Role::all();

        $eventAdmins = \App\Models\EventAdmin::with('user')->get();

        return view('superadmin.index', compact('assignments', 'users', 'courses', 'roles', 'eventAdmins'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:user,user_id',
            'course_id' => 'required|exists:course,course_id',
            'role_id'   => 'required|exists:roles,role_id',
        ]);

        $exists = UserCourseRole::where('user_id', $request->user_id)
            ->where('course_id', $request->course_id)
            ->where('role_id', $request->role_id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['duplicate' => __('pages.duplicate_role_assignment')])
                ->withInput();
        }

        UserCourseRole::create($request->only('user_id', 'course_id', 'role_id'));

        return redirect()->route('superadmin.index')->with('success', __('pages.role_assigned'));
    }

    public function destroy(string $id)
    {
        UserCourseRole::findOrFail($id)->delete();

        return redirect()->route('superadmin.index')->with('success', __('pages.role_unassigned'));
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'role_name'       => 'required|string|max:30|unique:roles,role_name',
            'role_decription' => 'required|string|max:25',
        ]);

        Role::create($request->only('role_name', 'role_decription'));

        return redirect()->route('superadmin.index')->with('success', __('pages.role_created'));
    }

    public function destroyRole(string $id)
    {
        Role::findOrFail($id)->delete();

        return redirect()->route('superadmin.index')->with('success', __('pages.role_deleted'));
    }

    public function storeCourse(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'year'        => 'required|integer|min:2000|max:2100',
            'default_session_start_time' => 'required|date_format:H:i',
        ]);

        Course::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'year' => $request->input('year'),
            'default_session_start_time' => $request->input('default_session_start_time').':00',
        ]);

        return redirect()->route('superadmin.index')->with('success', __('pages.course_created'));
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

        return redirect()->route('superadmin.index')->with('success', __('pages.course_deleted'));
    }

    public function storeEventAdmin(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
        ]);

        \App\Models\EventAdmin::firstOrCreate(
            ['user_id' => $data['user_id']],
            ['assigned_by_id' => auth()->id(), 'assigned_at' => now()]
        );

        \App\Services\EventAuditService::log('admin.assign', 'success', [
            'target_user_id' => $data['user_id'],
        ]);

        return redirect()->route('superadmin.index')->with('success', __('events.event_admin_assigned'));
    }

    public function destroyEventAdmin(string $userId)
    {
        \App\Models\EventAdmin::where('user_id', $userId)->delete();

        \App\Services\EventAuditService::log('admin.unassign', 'success', [
            'target_user_id' => (int) $userId,
        ]);

        return redirect()->route('superadmin.index')->with('success', __('events.event_admin_removed'));
    }

    public function flushAllSessions(Request $request)
    {
        $result = ForceLogoutService::logoutAllUsers($request->session()->getId());

        return redirect()
            ->route('superadmin.index')
            ->with('success', __('pages.force_logout_success', [
                'sessions' => $result['sessions_cleared'],
                'tokens'   => $result['remember_tokens_cleared'],
                'driver'   => $result['driver'],
            ]));
    }

    public function impersonate(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:user,user_id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        ImpersonationService::start($request->user(), $user, $request);

        return redirect()
            ->route('dashboard')
            ->with('success', __('pages.impersonate_started', [
                'name' => User::fullNameFromParts($user->first_name, $user->second_name, $user->third_name),
            ]));
    }

    public function stopImpersonating(Request $request)
    {
        ImpersonationService::stop($request);

        return redirect()
            ->route('superadmin.index')
            ->with('success', __('pages.impersonate_stopped'));
    }
}
