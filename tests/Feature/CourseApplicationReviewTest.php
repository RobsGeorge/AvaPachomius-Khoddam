<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\CourseApplicationFieldReview;
use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationFormStep;
use App\Models\CourseUserApplicationStatus;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Services\CourseApplicationFormService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class CourseApplicationReviewTest extends EventModuleTestCase
{
    /** @param array{student: \App\Models\Role, admin: \App\Models\Role} $roles */
    protected function createEnabledForm(Course $course, array $roles): CourseApplicationForm
    {
        $formService = app(CourseApplicationFormService::class);
        $form = $formService->getOrCreateForCourse($course);
        $form->update([
            'is_enabled' => true,
            'title' => 'Apply to '.$course->title,
            'default_role_id' => $roles['student']->role_id,
        ]);

        $step = CourseApplicationFormStep::create([
            'form_id' => $form->id,
            'title' => 'Personal info',
            'order_index' => 0,
        ]);

        CourseApplicationFormField::create([
            'step_id' => $step->id,
            'field_key' => 'motivation',
            'type' => CourseApplicationFormField::TYPE_LONG_TEXT,
            'label' => 'Why join?',
            'required' => true,
            'order_index' => 0,
            'config' => ['max_length' => 500],
        ]);

        CourseApplicationFormField::create([
            'step_id' => $step->id,
            'field_key' => 'experience',
            'type' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'label' => 'Experience',
            'required' => true,
            'order_index' => 1,
        ]);

        return $form->fresh(['steps.fields']);
    }

    public function test_admin_can_build_form_and_enable_it(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'course-app-admin@example.com']);
        $course = $this->createCourse(['title' => 'Year Two']);
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $this->actingAs($admin)
            ->get(route('admin.courses.application-form.edit', $course->course_id))
            ->assertOk()
            ->assertSee(__('course_applications.form_title'));

        $this->actingAs($admin)
            ->put(route('admin.courses.application-form.update', $course->course_id), [
                'is_enabled' => '1',
                'title' => 'Join Year Two',
                'description' => 'Application required',
                'default_role_id' => $roles['student']->role_id,
            ])
            ->assertRedirect();

        $form = CourseApplicationForm::query()->where('course_id', $course->course_id)->first();
        $this->assertNotNull($form);
        $this->assertTrue($form->is_enabled);
    }

    public function test_student_submit_creates_pending_application_and_notifies_staff(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $course = $this->createCourse(['title' => 'Apply Course']);
        $admin = $this->createUser(['email' => 'course-app-staff@example.com']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->createEnabledForm($course, $roles);

        $student = $this->createUser([
            'email' => 'course-app-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $this->createCourse(), $roles['student']);

        $this->actingAs($student)
            ->post(route('courses.apply.store', $course->course_id), [
                'fields' => [
                    'motivation' => 'I want to learn more.',
                    'experience' => 'Two years serving',
                ],
            ])
            ->assertRedirect(route('courses.application.status', $course->course_id));

        $application = CourseApplication::query()
            ->where('user_id', $student->user_id)
            ->where('course_id', $course->course_id)
            ->first();

        $this->assertNotNull($application);
        $this->assertSame(CourseApplication::STATUS_PENDING_REVIEW, $application->status);
        $this->assertSame('I want to learn more.', $application->snapshot['motivation']);

        $status = CourseUserApplicationStatus::query()
            ->where('user_id', $student->user_id)
            ->where('course_id', $course->course_id)
            ->first();
        $this->assertSame(CourseApplication::STATUS_PENDING_REVIEW, $status->application_status);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->user_id)
                ->where('type', 'course_application_submitted')
                ->exists()
        );
    }

    public function test_middleware_blocks_curriculum_until_approved(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse(['title' => 'Gated Course']);
        $this->createEnabledForm($course, $roles);

        $student = $this->createUser([
            'email' => 'gated-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $this->createCourse(), $roles['student']);

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertRedirect(route('courses.apply', $course->course_id));
    }

    public function test_admin_can_request_corrections_and_student_resubmits(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'course-correct-admin@example.com']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $form = $this->createEnabledForm($course, $roles);

        $student = $this->createUser([
            'email' => 'course-correct-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $this->createCourse(), $roles['student']);

        $this->actingAs($student)
            ->post(route('courses.apply.store', $course->course_id), [
                'fields' => [
                    'motivation' => 'First attempt',
                    'experience' => 'Beginner',
                ],
            ]);

        $application = CourseApplication::query()
            ->where('user_id', $student->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.course-applications.request-corrections', $application), [
                'fields' => [
                    'motivation' => ['status' => 'accepted', 'comment' => ''],
                    'experience' => ['status' => 'rejected', 'comment' => 'Add more detail'],
                ],
            ])
            ->assertRedirect(route('admin.course-applications.show', $application));

        $application->refresh();
        $this->assertSame(CourseApplication::STATUS_NEEDS_CORRECTION, $application->status);

        $this->actingAs($student)
            ->put(route('courses.application.update', $course->course_id), [
                'fields' => [
                    'motivation' => 'First attempt',
                    'experience' => 'Two years in ministry',
                ],
            ])
            ->assertRedirect(route('courses.application.status', $course->course_id));

        $this->assertSame(2, CourseApplication::query()->where('user_id', $student->user_id)->where('course_id', $course->course_id)->count());
    }

    public function test_admin_can_approve_application_and_enroll_student(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'course-approve-admin@example.com']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->createEnabledForm($course, $roles);

        $student = $this->createUser([
            'email' => 'course-approve-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $this->createCourse(), $roles['student']);

        $this->actingAs($student)
            ->post(route('courses.apply.store', $course->course_id), [
                'fields' => [
                    'motivation' => 'Ready to join',
                    'experience' => 'Serving',
                ],
            ]);

        $application = CourseApplication::query()
            ->where('user_id', $student->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.course-applications.approve', $application), [
                'fields' => [
                    'motivation' => ['status' => 'accepted', 'comment' => ''],
                    'experience' => ['status' => 'accepted', 'comment' => ''],
                ],
            ])
            ->assertRedirect(route('admin.course-applications.index', ['filter' => CourseApplication::STATUS_APPROVED]));

        $application->refresh();
        $this->assertSame(CourseApplication::STATUS_APPROVED, $application->status);
        $this->assertTrue(
            UserCourseRole::query()
                ->where('user_id', $student->user_id)
                ->where('course_id', $course->course_id)
                ->where('role_id', $roles['student']->role_id)
                ->exists()
        );

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk();
    }

    public function test_admin_can_reject_and_restore_application(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'course-reject-admin@example.com']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->createEnabledForm($course, $roles);

        $student = $this->createUser([
            'email' => 'course-reject-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $this->createCourse(), $roles['student']);

        $this->actingAs($student)
            ->post(route('courses.apply.store', $course->course_id), [
                'fields' => [
                    'motivation' => 'Apply',
                    'experience' => 'Some',
                ],
            ]);

        $application = CourseApplication::query()
            ->where('user_id', $student->user_id)
            ->where('course_id', $course->course_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.course-applications.reject', $application), [
                'overall_rejection_note' => 'Not ready yet',
            ])
            ->assertRedirect(route('admin.course-applications.index', ['filter' => CourseApplication::STATUS_REJECTED]));

        $application->refresh();
        $this->assertSame(CourseApplication::STATUS_REJECTED, $application->status);

        $this->actingAs($admin)
            ->post(route('admin.course-applications.restore', $application), [
                'target_status' => CourseApplication::STATUS_PENDING_REVIEW,
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(CourseApplication::STATUS_PENDING_REVIEW, $application->status);
        $this->assertSame(0, CourseApplicationFieldReview::query()->where('application_id', $application->id)->count());
    }

    public function test_course_without_enabled_form_allows_curriculum_access_for_enrolled_student(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser([
            'email' => 'no-form-student@example.com',
            'application_status' => 'approved',
            'is_verified' => true,
        ]);
        $this->assignCourseRole($student, $course, $roles['student']);

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk();
    }
}
