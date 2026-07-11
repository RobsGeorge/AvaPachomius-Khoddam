<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserCourseRole;
use App\Models\User;
use App\Models\Course;
use App\Models\Role;
use App\Services\PendingRegistrationService;
use App\Services\CoursePermissionResolver;
use App\Services\RoleTemplateService;
use Illuminate\Support\Facades\Password;

class UserCourseRoleController extends Controller
{
    public function index()
    {
        $assignments = UserCourseRole::with(['user', 'course', 'role'])->get();

        $accountStatuses = $assignments->mapWithKeys(function (UserCourseRole $assignment) {
            $status = $assignment->user
                ? PendingRegistrationService::accountStatus($assignment->user)
                : PendingRegistrationService::unknownAccountStatus();

            return [$assignment->user_course_role_id => $status];
        });

        return view('user_course_roles.index', compact('assignments', 'accountStatuses'));
    }

    public function create()
    {
        $users   = User::orderBy('first_name')->get();
        $courses = Course::orderBy('title')->get();
        $roles   = Role::all();
        return view('user_course_roles.create', compact('users', 'courses', 'roles'));
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
                ->withErrors(['duplicate' => 'هذا المستخدم لديه هذا الدور في هذه الدورة بالفعل.'])
                ->withInput();
        }

        UserCourseRole::updateOrCreate(
            ['user_id' => $request->user_id, 'course_id' => $request->course_id],
            ['role_id' => $request->role_id]
        );

        $course = Course::find($request->course_id);
        if ($course) {
            app(CoursePermissionResolver::class)->bumpCoursePermissionsVersion($course);
        }

        return redirect()->route('user-course-roles.index')->with('success', 'تم تعيين الدور بنجاح');
    }

    public function destroy(string $id)
    {
        UserCourseRole::findOrFail($id)->delete();
        return redirect()->route('user-course-roles.index')->with('success', 'تم إلغاء تعيين الدور');
    }

    public function sendRegistrationLink(User $user)
    {
        if (! PendingRegistrationService::isPending($user)) {
            return redirect()
                ->route('user-course-roles.index')
                ->with('warning', __('pages.account_status_already_active'));
        }

        if (! $user->email) {
            return redirect()
                ->route('user-course-roles.index')
                ->with('error', __('pages.account_status_no_email'));
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()
                ->route('user-course-roles.index')
                ->with('error', __($status));
        }

        return redirect()
            ->route('user-course-roles.index')
            ->with('success', __('pages.account_setup_email_sent', ['email' => $user->email]));
    }
}
