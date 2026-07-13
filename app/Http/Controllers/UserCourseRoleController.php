<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\CoursePermissionResolver;
use App\Services\CourseRoleAssignmentService;
use App\Services\PendingRegistrationService;
use App\Services\RolesHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class UserCourseRoleController extends Controller
{
    public function index()
    {
        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'));
    }

    public function create()
    {
        return redirect(app(RolesHubService::class)->hubUrl(null, 'assignments'));
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
            app(CoursePermissionResolver::class)->bumpCoursePermissionsVersion($course);
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
            app(CoursePermissionResolver::class)->bumpCoursePermissionsVersion($course);
        }

        return redirect(app(RolesHubService::class)->hubUrl($course, 'assignments'))
            ->with('success', 'تم إلغاء تعيين الدور');
    }

    public function sendRegistrationLink(User $user)
    {
        if (! PendingRegistrationService::isPending($user)) {
            return redirect()
                ->route('roles.hub', ['section' => 'assignments'])
                ->with('warning', __('pages.account_status_already_active'));
        }

        if (! $user->email) {
            return redirect()
                ->route('roles.hub', ['section' => 'assignments'])
                ->with('error', __('pages.account_status_no_email'));
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()
                ->route('roles.hub', ['section' => 'assignments'])
                ->with('error', __($status));
        }

        return redirect()
            ->route('roles.hub', ['section' => 'assignments'])
            ->with('success', __('pages.account_setup_email_sent', ['email' => $user->email]));
    }
}
