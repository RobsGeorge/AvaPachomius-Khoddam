<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\AccessLedgerEntry;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\User;
use App\Support\NavigationHub;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class AdminPasswordResetTest extends EventModuleTestCase
{
    private function makeCourseAdmin(Course $course, string $email): User
    {
        $role = $this->courseRoleWithPermissions($course, 'admin', [
            'role.manage',
            'roster.view',
            'roster.password_reset',
        ]);
        $admin = $this->createUser(['email' => $email]);
        $this->assignCourseRole($admin, $course, $role);

        return $admin;
    }

    private function makeStudent(Course $course, array $overrides = []): User
    {
        $role = $this->courseRoleWithPermissions($course, 'student', [
            'course.access',
            'exam.take',
            'assignment.submit',
        ]);
        $student = $this->createUser($overrides);
        $this->assignCourseRole($student, $course, $role);

        return $student;
    }

    public function test_guest_is_redirected_from_the_panel(): void
    {
        $this->get(route('students.password-reset.index'))->assertRedirect();
        $this->get(route('superadmin.password-reset.index'))->assertRedirect();
    }

    public function test_student_cannot_open_the_panel(): void
    {
        $course = $this->createCourse(['title' => 'Reset Student Deny']);
        $student = $this->makeStudent($course, ['email' => 'reset-deny-student@example.com']);

        $this->actingAs($student)
            ->get(route('students.password-reset.index'))
            ->assertForbidden();
    }

    public function test_instructor_without_permission_cannot_open_the_panel(): void
    {
        $course = $this->createCourse(['title' => 'Reset Instructor Deny']);
        $role = $this->courseRoleWithPermissions($course, 'instructor', [
            'roster.view',
            'assignment.manage',
        ]);
        $instructor = $this->createUser(['email' => 'reset-deny-instructor@example.com']);
        $this->assignCourseRole($instructor, $course, $role);

        $this->actingAs($instructor)
            ->get(route('students.password-reset.index'))
            ->assertForbidden();
    }

    public function test_course_admin_can_search_and_send_reset_email(): void
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Reset Admin Course']);
        $admin = $this->makeCourseAdmin($course, 'reset-course-admin@example.com');
        $student = $this->makeStudent($course, [
            'email' => 'reset-target-student@example.com',
            'first_name' => 'Nader',
            'second_name' => 'Fouad',
        ]);

        $this->actingAs($admin)
            ->get(route('students.password-reset.index', ['q' => 'Nader']))
            ->assertOk()
            ->assertSee(__('students.password_reset_title'), false)
            ->assertSee('Nader Fouad', false)
            ->assertSee($student->email, false);

        $this->actingAs($admin)
            ->from(route('students.password-reset.index', ['q' => 'Nader']))
            ->post(route('students.password-reset.store'), ['user_id' => $student->user_id])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail) => $mail->hasTo($student->email)
        );

        $this->assertTrue(
            AccessLedgerEntry::query()
                ->where('action', 'recovery')
                ->where('actor_id', $admin->user_id)
                ->where('subject_id', $student->user_id)
                ->exists()
        );

        $this->assertTrue(
            ActivityLog::query()
                ->where('route_name', 'auth.admin_password_reset')
                ->where('user_id', $admin->user_id)
                ->exists()
        );
    }

    public function test_admin_sent_reset_link_can_set_a_new_password(): void
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Reset Token Course']);
        $admin = $this->makeCourseAdmin($course, 'reset-token-admin@example.com');
        $student = $this->makeStudent($course, [
            'email' => 'reset-token-student@example.com',
            'password' => Hash::make('OldPass1!'),
        ]);

        $this->actingAs($admin)
            ->post(route('students.password-reset.store'), ['user_id' => $student->user_id])
            ->assertSessionHas('success');

        $resetUrl = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$resetUrl, $student) {
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo($student->email);
        });

        $this->assertNotEmpty($resetUrl);
        $path = parse_url($resetUrl, PHP_URL_PATH);
        $this->assertIsString($path);
        $token = basename($path);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass1!', $student->fresh()->password));
    }

    public function test_course_admin_cannot_reset_a_student_in_another_course(): void
    {
        Mail::fake();

        $ownCourse = $this->createCourse(['title' => 'Reset Own Course']);
        $otherCourse = $this->createCourse(['title' => 'Reset Other Course']);
        $admin = $this->makeCourseAdmin($ownCourse, 'reset-scope-admin@example.com');
        $outsider = $this->makeStudent($otherCourse, ['email' => 'reset-outsider@example.com']);

        $this->actingAs($admin)
            ->get(route('students.password-reset.index', ['q' => 'reset-outsider']))
            ->assertOk()
            ->assertDontSee('reset-outsider@example.com', false);

        $this->actingAs($admin)
            ->post(route('students.password-reset.store'), ['user_id' => $outsider->user_id])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_superadmin_panel_sends_reset_to_any_user(): void
    {
        Mail::fake();

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'reset-super@example.com',
        ]);
        $student = $this->createUser([
            'email' => 'reset-any-user@example.com',
            'first_name' => 'Mariam',
            'second_name' => 'Hanna',
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.password-reset.index'))
            ->assertOk()
            ->assertSee(__('students.password_reset_title'), false);

        $this->actingAs($super)
            ->get(route('superadmin.password-reset.index', ['q' => 'Mariam']))
            ->assertOk()
            ->assertSee('Mariam Hanna', false);

        $this->actingAs($super)
            ->post(route('superadmin.password-reset.store'), ['user_id' => $student->user_id])
            ->assertRedirect(route('superadmin.password-reset.index', ['q' => $student->email]))
            ->assertSessionHas('success');

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail) => $mail->hasTo($student->email)
        );
    }

    public function test_non_superadmin_cannot_open_console_panel(): void
    {
        $course = $this->createCourse(['title' => 'Reset Console Deny']);
        $admin = $this->makeCourseAdmin($course, 'reset-console-admin@example.com');

        $this->actingAs($admin)
            ->get(route('superadmin.password-reset.index'))
            ->assertForbidden();
    }

    public function test_missing_email_returns_an_error(): void
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Reset No Email']);
        $admin = $this->makeCourseAdmin($course, 'reset-no-email-admin@example.com');
        $student = $this->makeStudent($course, [
            'email' => 'reset-blank-email@example.com',
        ]);
        $student->email = '';
        $student->save();

        $this->actingAs($admin)
            ->post(route('students.password-reset.store'), ['user_id' => $student->user_id])
            ->assertSessionHas('error', __('students.password_reset_no_email'));

        Mail::assertNothingSent();
    }

    public function test_roster_lists_send_button_for_course_admin(): void
    {
        $course = $this->createCourse(['title' => 'Reset Roster Course']);
        $admin = $this->makeCourseAdmin($course, 'reset-roster-admin@example.com');
        $student = $this->makeStudent($course, [
            'email' => 'reset-roster-student@example.com',
            'first_name' => 'Youssef',
            'second_name' => 'Adel',
        ]);

        $this->actingAs($admin)
            ->get(route('students.roster', ['course' => $course->course_id]))
            ->assertOk()
            ->assertSee(__('students.password_reset_send'), false)
            ->assertSee($student->email, false);
    }

    public function test_navigation_exposes_the_panel_for_admins(): void
    {
        $course = $this->createCourse(['title' => 'Reset Nav Course']);
        $admin = $this->makeCourseAdmin($course, 'reset-nav-admin@example.com');
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'reset-nav-super@example.com',
        ]);

        $academicUrls = collect(NavigationHub::academicLinks($admin))->pluck('url');
        $this->assertTrue($academicUrls->contains(route('students.password-reset.index')));

        $this->actingAs($admin)
            ->get(route('hubs.academic'))
            ->assertOk()
            ->assertSee(__('students.password_reset_title'), false);

        $superLabels = collect(NavigationHub::superadminSections($super))
            ->flatMap(fn ($section) => collect($section['links'])->pluck('label'));
        $this->assertTrue($superLabels->contains(__('pages.superadmin_password_reset_title')));

        $this->actingAs($super)
            ->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('pages.superadmin_password_reset_title'), false);

        $this->actingAs($super)
            ->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(route('superadmin.password-reset.index'), false);
    }

    public function test_closed_course_still_allows_password_reset(): void
    {
        Mail::fake();

        $course = $this->createCourse([
            'title' => 'Reset Closed Course',
            'status' => Course::STATUS_CLOSED,
        ]);
        $admin = $this->makeCourseAdmin($course, 'reset-closed-admin@example.com');
        $student = $this->makeStudent($course, ['email' => 'reset-closed-student@example.com']);

        $this->actingAs($admin)
            ->post(route('students.password-reset.store'), ['user_id' => $student->user_id])
            ->assertSessionHas('success');

        Mail::assertSent(ResetPasswordMail::class);
    }
}
