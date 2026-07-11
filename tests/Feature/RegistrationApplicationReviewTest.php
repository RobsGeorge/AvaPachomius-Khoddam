<?php

namespace Tests\Feature;

use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\PendingRegistrationService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class RegistrationApplicationReviewTest extends EventModuleTestCase
{
    protected function createPendingApplicant(array $overrides = []): User
    {
        $user = $this->createUser(array_merge([
            'is_verified' => false,
            'registration_completed' => true,
            'application_status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'first_name' => 'متقدم',
            'second_name' => 'اختبار',
            'third_name' => 'جديد',
        ], $overrides));

        RegistrationApplication::create([
            'user_id' => $user->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => [
                'first_name' => $user->first_name,
                'second_name' => $user->second_name,
                'third_name' => $user->third_name,
                'national_id' => $user->national_id,
                'mobile_number' => $user->mobile_number,
                'email' => $user->email,
                'job' => $user->job,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'profile_photo' => $user->profile_photo,
            ],
            'version' => 1,
            'submitted_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_mark_completed_queues_application_for_review(): void
    {
        Mail::fake();

        $user = $this->createUser([
            'is_verified' => false,
            'registration_completed' => false,
            'application_status' => null,
        ]);

        PendingRegistrationService::markCompleted($user);

        $user->refresh();
        $this->assertTrue($user->registration_completed);
        $this->assertFalse($user->is_verified);
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $user->application_status);

        $application = RegistrationApplication::query()->where('user_id', $user->user_id)->first();
        $this->assertNotNull($application);
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $application->status);
        $this->assertSame(0, UserCourseRole::query()->where('user_id', $user->user_id)->count());
    }

    public function test_pending_applicant_is_redirected_from_dashboard(): void
    {
        $applicant = $this->createPendingApplicant();

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertRedirect(route('application.status'));
    }

    public function test_pending_applicant_can_view_status_page(): void
    {
        $applicant = $this->createPendingApplicant();

        $this->actingAs($applicant)
            ->get(route('application.status'))
            ->assertOk()
            ->assertSee(__('registration_review.waiting_title'));
    }

    public function test_completed_unapproved_user_can_log_in(): void
    {
        $applicant = $this->createPendingApplicant(['email' => 'pending-login@example.com']);

        $this->post(route('login'), [
            'email' => 'pending-login@example.com',
            'password' => 'password',
        ])->assertRedirect(route('application.status'));

        $this->assertAuthenticatedAs($applicant);
    }

    public function test_admin_can_view_registration_queue(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'reg-queue-admin@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->createPendingApplicant(['email' => 'queue-applicant@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.registration-applications.index'))
            ->assertOk()
            ->assertSee(__('registration_review.queue_title'));
    }

    public function test_admin_can_request_corrections(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'reg-correct-admin@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $applicant = $this->createPendingApplicant(['email' => 'reg-correct-applicant@example.com']);
        $application = RegistrationApplication::query()->where('user_id', $applicant->user_id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.registration-applications.request-corrections', $application), [
                'fields' => [
                    'first_name' => ['status' => 'accepted', 'comment' => ''],
                    'second_name' => ['status' => 'accepted', 'comment' => ''],
                    'third_name' => ['status' => 'accepted', 'comment' => ''],
                    'national_id' => ['status' => 'rejected', 'comment' => 'Invalid ID'],
                    'mobile_number' => ['status' => 'accepted', 'comment' => ''],
                    'email' => ['status' => 'accepted', 'comment' => ''],
                    'job' => ['status' => 'accepted', 'comment' => ''],
                    'date_of_birth' => ['status' => 'accepted', 'comment' => ''],
                    'profile_photo' => ['status' => 'accepted', 'comment' => ''],
                ],
            ])
            ->assertRedirect(route('admin.registration-applications.show', $application));

        $applicant->refresh();
        $application->refresh();
        $this->assertSame(RegistrationApplication::STATUS_NEEDS_CORRECTION, $applicant->application_status);
        $this->assertSame(RegistrationApplication::STATUS_NEEDS_CORRECTION, $application->status);
    }

    public function test_applicant_can_resubmit_after_corrections_requested(): void
    {
        $applicant = $this->createPendingApplicant([
            'email' => 'resubmit-applicant@example.com',
            'application_status' => RegistrationApplication::STATUS_NEEDS_CORRECTION,
        ]);

        $application = RegistrationApplication::query()->where('user_id', $applicant->user_id)->firstOrFail();
        $application->update(['status' => RegistrationApplication::STATUS_NEEDS_CORRECTION]);
        RegistrationApplicationFieldReview::create([
            'application_id' => $application->id,
            'field_key' => 'national_id',
            'status' => RegistrationApplicationFieldReview::STATUS_REJECTED,
            'comment' => 'Fix national ID',
        ]);

        $this->actingAs($applicant)
            ->put(route('application.update'), [
                'first_name' => 'متقدم',
                'second_name' => 'محدث',
                'third_name' => 'جديد',
                'national_id' => '29001010101010',
                'mobile_number' => '1012345678',
                'email' => $applicant->email,
                'job' => $applicant->job,
                'date_of_birth' => '2000-01-01',
            ])
            ->assertRedirect(route('application.status'));

        $applicant->refresh();
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $applicant->application_status);
        $this->assertSame('متقدم', $applicant->first_name);
        $this->assertSame(2, RegistrationApplication::query()->where('user_id', $applicant->user_id)->count());
    }

    public function test_admin_can_approve_application_and_assign_role(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'reg-approve-admin@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $applicant = $this->createPendingApplicant(['email' => 'reg-approve-applicant@example.com']);
        $application = RegistrationApplication::query()->where('user_id', $applicant->user_id)->firstOrFail();

        $acceptedFields = [];
        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $field) {
            $acceptedFields[$field] = ['status' => 'accepted', 'comment' => ''];
        }

        $this->actingAs($admin)
            ->post(route('admin.registration-applications.approve', $application), [
                'fields' => $acceptedFields,
                'course_id' => $course->course_id,
                'role_id' => $roles['student']->role_id,
            ])
            ->assertRedirect(route('admin.registration-applications.index', ['filter' => RegistrationApplication::STATUS_APPROVED]));

        $applicant->refresh();
        $application->refresh();
        $this->assertTrue($applicant->is_verified);
        $this->assertSame(RegistrationApplication::STATUS_APPROVED, $applicant->application_status);
        $this->assertSame(RegistrationApplication::STATUS_APPROVED, $application->status);
        $this->assertTrue(
            UserCourseRole::query()
                ->where('user_id', $applicant->user_id)
                ->where('course_id', $course->course_id)
                ->where('role_id', $roles['student']->role_id)
                ->exists()
        );
    }

    public function test_admin_can_soft_reject_application(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'reg-reject-admin@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $applicant = $this->createPendingApplicant(['email' => 'reg-reject-applicant@example.com']);
        $application = RegistrationApplication::query()->where('user_id', $applicant->user_id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.registration-applications.reject', $application), [
                'overall_rejection_note' => 'Incomplete documentation',
            ])
            ->assertRedirect(route('admin.registration-applications.index', ['filter' => RegistrationApplication::STATUS_REJECTED]));

        $applicant->refresh();
        $application->refresh();
        $this->assertFalse($applicant->is_verified);
        $this->assertSame(RegistrationApplication::STATUS_REJECTED, $applicant->application_status);
        $this->assertSame('Incomplete documentation', $application->overall_rejection_note);
        $this->assertNotNull(User::query()->find($applicant->user_id));
    }

    public function test_admin_can_restore_rejected_application(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'reg-restore-admin@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $applicant = $this->createPendingApplicant([
            'email' => 'reg-restore-applicant@example.com',
            'application_status' => RegistrationApplication::STATUS_REJECTED,
        ]);
        $application = RegistrationApplication::query()->where('user_id', $applicant->user_id)->firstOrFail();
        $application->update([
            'status' => RegistrationApplication::STATUS_REJECTED,
            'overall_rejection_note' => 'Try again later',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.registration-applications.restore', $application), [
                'target_status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            ])
            ->assertRedirect();

        $applicant->refresh();
        $application->refresh();
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $applicant->application_status);
        $this->assertSame(RegistrationApplication::STATUS_PENDING_REVIEW, $application->status);
        $this->assertNull($application->overall_rejection_note);
    }
}
