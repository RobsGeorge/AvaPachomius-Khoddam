<?php

namespace Tests\Feature;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationFormStep;
use App\Models\OtpCode;
use App\Models\RegistrationApplication;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Mail\SendOTPEmail;
use App\Services\CourseApplicationFormService;
use App\Services\PendingRegistrationService;
use App\Services\RegistrationApplicationService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class RegistrationEnrollmentRoutingTest extends EventModuleTestCase
{
    private const TEST_PASSWORD = 'SecurePass1!';

  /** @param array{student?: Role, admin?: Role} $roles */
    protected function createEnabledForm(Course $course, array $roles = []): CourseApplicationForm
    {
        $studentRole = $roles['student'] ?? null;
        if (! $studentRole || (int) $studentRole->course_id !== (int) $course->course_id) {
            $studentRole = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);
        }

        $formService = app(CourseApplicationFormService::class);
        $form = $formService->getOrCreateForCourse($course);
        $form->update([
            'is_enabled' => true,
            'title' => 'Apply to '.$course->title,
            'default_role_id' => $studentRole->role_id,
        ]);

        $step = CourseApplicationFormStep::create([
            'form_id' => $form->id,
            'title' => 'Personal info',
            'order_index' => 0,
        ]);

        CourseApplicationFormField::create([
            'step_id' => $step->id,
            'field_key' => 'first_name',
            'type' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'label' => 'First name',
            'required' => true,
            'order_index' => 0,
        ]);

        CourseApplicationFormField::create([
            'step_id' => $step->id,
            'field_key' => 'motivation',
            'type' => CourseApplicationFormField::TYPE_LONG_TEXT,
            'label' => 'Why join?',
            'required' => true,
            'order_index' => 1,
        ]);

        return $form->fresh(['steps.fields']);
    }

    /** @return array{user: User, course: Course, service: ChurchService} */
    private function seedEnrollmentTarget(): array
    {
        $service = $this->createService(['title' => 'Servants Prep']);
        $course = $this->createCourse([
            'title' => 'Year One',
            'service_id' => $service->service_id,
        ]);
        $this->createEnabledForm($course);

        return compact('service', 'course');
    }

    private function registerApplicant(string $email = 'new-applicant@example.com'): User
    {
        Mail::fake();

        $this->post(route('register.store'), [
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => $email,
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ])->assertRedirect();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function completePasswordStep(User $user): void
    {
        $otp = OtpCode::query()->where('user_id', $user->user_id)->value('code');

        if (! $otp) {
            OtpCode::create([
                'user_id' => $user->user_id,
                'code' => '123456',
                'expires_at' => now()->addMinutes(10),
            ]);
            $otp = '123456';
        }

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => $otp,
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        $this->post(route('password.set.store'), [
            'user_id' => $user->user_id,
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));
    }

    public function test_setting_password_does_not_reopen_otp_when_no_courses_are_open(): void
    {
        Mail::fake();

        $user = $this->registerApplicant('no-courses@example.com');
        $this->completePasswordStep($user);

        Mail::assertSent(SendOTPEmail::class, 1);

        $this->get(route('register.enrollment', ['user_id' => $user->user_id]))
            ->assertOk()
            ->assertSee(__('register.enrollment_no_courses_available'), false)
            ->assertDontSee(__('auth.otp_enter'), false);

        $this->get(route('password.set', ['user_id' => $user->user_id]))
            ->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        $this->get(route('otp.verify', ['user_id' => $user->user_id]))
            ->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        $this->post(route('otp.resend'), ['user_id' => $user->user_id])
            ->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        Mail::assertSent(SendOTPEmail::class, 1);
        $this->assertDatabaseMissing('otp_code', ['user_id' => $user->user_id]);
    }

    public function test_active_course_without_enabled_form_appears_on_enrollment_page(): void
    {
        Mail::fake();

        $service = $this->createService(['title' => 'Existing Service']);
        $course = $this->createCourse([
            'title' => 'Existing Year Course',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->assertFalse(app(\App\Services\CourseApplicationService::class)->formEnabledForCourse($course->course_id));

        $user = $this->registerApplicant('legacy-course@example.com');
        $this->completePasswordStep($user);

        $this->get(route('register.enrollment', ['user_id' => $user->user_id]))
            ->assertOk()
            ->assertDontSee(__('register.enrollment_no_courses_available'), false)
            ->assertSee('Existing Service', false)
            ->assertSee('Existing Year Course', false);
    }

    public function test_enrollment_submit_provisions_form_for_course_without_builder_setup(): void
    {
        Mail::fake();

        $service = $this->createService(['title' => 'Open Service']);
        $course = $this->createCourse([
            'title' => 'Open Year',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $user = $this->registerApplicant('auto-form@example.com');
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $user->refresh();
        $this->assertTrue($user->registration_completed);
        $this->assertSame($course->course_id, $user->registration_intent_course_id);

        $form = CourseApplicationForm::query()->where('course_id', $course->course_id)->first();
        $this->assertNotNull($form);
        $this->assertTrue($form->is_enabled);
        $this->assertNotNull($form->default_role_id);

        $this->assertDatabaseHas('course_applications', [
            'user_id' => $user->user_id,
            'course_id' => $course->course_id,
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_closed_course_does_not_appear_on_enrollment_page(): void
    {
        Mail::fake();

        $service = $this->createService(['title' => 'Closed Service']);
        $this->createCourse([
            'title' => 'Closed Year Course',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_CLOSED,
        ]);

        $user = $this->registerApplicant('closed-course@example.com');
        $this->completePasswordStep($user);

        $this->get(route('register.enrollment', ['user_id' => $user->user_id]))
            ->assertOk()
            ->assertSee(__('register.enrollment_no_courses_available'), false)
            ->assertDontSee('Closed Year Course', false);
    }

    public function test_orphan_active_course_appears_under_default_service(): void
    {
        Mail::fake();

        $default = ChurchService::ensureDefault();
        $course = $this->createCourse([
            'title' => 'Orphan Active Course',
            'service_id' => null,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $user = $this->registerApplicant('orphan-course@example.com');
        $this->completePasswordStep($user);

        $this->getJson(route('register.enrollment.courses', [
            'service_id' => $default->service_id,
        ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $course->course_id,
                'title' => 'Orphan Active Course',
            ]);
    }

    public function test_setting_password_does_not_resend_otp_or_return_to_otp_page(): void
    {
        Mail::fake();

        $this->seedEnrollmentTarget();
        $user = $this->registerApplicant('otp-loop@example.com');
        $this->completePasswordStep($user);

        Mail::assertSent(SendOTPEmail::class, 1);

        $this->get(route('register.enrollment', ['user_id' => $user->user_id]))
            ->assertOk()
            ->assertSee(__('register.enrollment_title'), false)
            ->assertDontSee(__('auth.otp_enter'), false);

        $this->get(route('otp.verify', ['user_id' => $user->user_id]))
            ->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        $this->post(route('password.set.store'), [
            'user_id' => $user->user_id,
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ])->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        $this->post(route('register.store'), [
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => $user->email,
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ])->assertRedirect(route('register.enrollment', ['user_id' => $user->user_id]));

        Mail::assertSent(SendOTPEmail::class, 1);
    }

    private function submitEnrollment(User $user, ChurchService $service, Course $course): void
    {
        $this->post(route('register.enrollment.store'), [
            'user_id' => $user->user_id,
            'service_id' => $service->service_id,
            'course_id' => $course->course_id,
        ])->assertRedirect(route('login'));
    }

    public function test_signup_creates_course_application_without_registration_application(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $user = $this->registerApplicant();
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $user->refresh();
        $this->assertTrue($user->registration_completed);
        $this->assertFalse($user->is_verified);
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $user->application_status);
        $this->assertSame($course->course_id, $user->registration_intent_course_id);

        $this->assertDatabaseHas('course_applications', [
            'user_id' => $user->user_id,
            'course_id' => $course->course_id,
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
        ]);

        $this->assertDatabaseMissing('registration_applications', [
            'user_id' => $user->user_id,
        ]);

        $application = CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->assertSame('محمد', $application->snapshot['first_name'] ?? null);
    }

    public function test_course_admin_approval_unlocks_platform_access(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $reviewerRole = $this->courseRoleWithPermissions($course, 'reviewer', ['course_application.review']);
        $admin = $this->createUser(['email' => 'course-reviewer@example.com']);
        $this->assignCourseRole($admin, $course, $reviewerRole);

        $user = $this->registerApplicant('unlock-me@example.com');
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $application = CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.course-applications.approve', $application), [
                'fields' => [
                    'first_name' => ['status' => 'accepted', 'comment' => ''],
                    'motivation' => ['status' => 'accepted', 'comment' => ''],
                ],
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->is_verified);
        $this->assertTrue(app(RegistrationApplicationService::class)->isApproved($user));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_course_reviewer_sees_only_their_course_queue(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $otherCourse = $this->createCourse([
            'title' => 'Other Course',
            'service_id' => $service->service_id,
        ]);

        $reviewerRole = $this->courseRoleWithPermissions($course, 'reviewer', ['course_application.review']);
        $reviewer = $this->createUser(['email' => 'scoped-reviewer@example.com']);
        $this->assignCourseRole($reviewer, $course, $reviewerRole);

        $outsiderRole = $this->courseRoleWithPermissions($otherCourse, 'outsider', ['course_application.review']);
        $outsider = $this->createUser(['email' => 'other-reviewer@example.com']);
        $this->assignCourseRole($outsider, $otherCourse, $outsiderRole);

        $user = $this->registerApplicant('scoped-applicant@example.com');
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $application = CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->actingAs($reviewer)
            ->get(route('admin.course-applications.show', $application))
            ->assertOk();

        $this->actingAs($outsider)
            ->get(route('admin.course-applications.show', $application))
            ->assertForbidden();
    }

    public function test_enrollment_rejects_course_from_wrong_service(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $otherService = $this->createService(['title' => 'Other Service']);

        $user = $this->registerApplicant('wrong-service@example.com');
        $this->completePasswordStep($user);

        $this->from(route('register.enrollment', ['user_id' => $user->user_id]))
            ->post(route('register.enrollment.store'), [
                'user_id' => $user->user_id,
                'service_id' => $otherService->service_id,
                'course_id' => $course->course_id,
            ])
            ->assertSessionHasErrors('course_id');

        $this->assertFalse($user->fresh()->registration_completed);
    }

    public function test_enrollment_courses_requires_session_marker(): void
    {
        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();

        $this->getJson(route('register.enrollment.courses', [
            'service_id' => $service->service_id,
        ]))->assertForbidden();
    }

    public function test_enrollment_courses_returns_eligible_courses_with_session_marker(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $user = $this->registerApplicant('courses-ajax@example.com');
        $this->completePasswordStep($user);

        $this->getJson(route('register.enrollment.courses', [
            'service_id' => $service->service_id,
        ]))
            ->assertOk()
            ->assertJson([
                'courses' => [
                    ['id' => $course->course_id, 'title' => $course->title],
                ],
            ]);
    }

    public function test_pending_signup_login_redirects_to_course_application_status(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $user = $this->registerApplicant('pending-login@example.com');
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $this->loginWithOtp($user, route('login'), [
            'email' => $user->email,
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('courses.application.status', $course->course_id));
    }

    public function test_course_admin_receives_submission_notification(): void
    {
        Mail::fake();

        ['service' => $service, 'course' => $course] = $this->seedEnrollmentTarget();
        $reviewerRole = $this->courseRoleWithPermissions($course, 'reviewer', ['course_application.review']);
        $admin = $this->createUser(['email' => 'notify-reviewer@example.com']);
        $this->assignCourseRole($admin, $course, $reviewerRole);

        $user = $this->registerApplicant('notify-applicant@example.com');
        $this->completePasswordStep($user);
        $this->submitEnrollment($user, $service, $course);

        $application = CourseApplication::query()
            ->where('user_id', $user->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->user_id)
                ->where('type', 'course_application_submitted')
                ->exists()
        );
    }
}
