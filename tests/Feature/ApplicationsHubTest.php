<?php

namespace Tests\Feature;

use App\Models\ChurchApplication;
use App\Models\CourseApplication;
use App\Models\CourseApplicationForm;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceApplication;
use App\Models\ServiceApplicationForm;
use App\Models\User;
use App\Models\UserSystemRole;
use App\Services\CourseApplicationFormService;
use App\Services\ServiceRoleAssignmentService;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class ApplicationsHubTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    private function grantSystemPermission(User $user, string $permissionKey): void
    {
        $perm = Permission::where('key', $permissionKey)->firstOrFail();
        $role = Role::create([
            'role_name' => 'Hub '.$permissionKey,
            'role_decription' => $permissionKey,
            'slug' => 'hub-'.str_replace('.', '-', $permissionKey).'-'.$user->user_id,
            'is_template' => false,
        ]);
        $role->permissions()->sync([$perm->permission_id]);
        UserSystemRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
        ]);
    }

    private function pendingCourseApplication(string $title = 'Hub Course'): CourseApplication
    {
        $course = $this->createCourse(['title' => $title]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);
        $form = app(CourseApplicationFormService::class)->getOrCreateForCourse($course);
        $form->update([
            'is_enabled' => true,
            'default_role_id' => $studentRole->role_id,
        ]);
        $applicant = $this->createUser(['email' => 'hub-course-applicant-'.uniqid().'@example.com']);

        return CourseApplication::create([
            'user_id' => $applicant->user_id,
            'course_id' => $course->course_id,
            'form_id' => $form->id,
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['motivation' => 'Join'],
            'version' => 1,
            'submitted_at' => now(),
        ]);
    }

    private function pendingServiceApplication(string $title = 'Hub Service'): ServiceApplication
    {
        $service = $this->createService(['title' => $title, 'title_en' => $title]);
        $memberRole = app(ServiceRoleAssignmentService::class)->memberRoleFor($service);
        $form = ServiceApplicationForm::create([
            'service_id' => $service->service_id,
            'title' => 'Apply',
            'default_role_id' => $memberRole->role_id,
            'is_enabled' => true,
        ]);
        $applicant = $this->createUser(['email' => 'hub-service-applicant-'.uniqid().'@example.com']);

        return ServiceApplication::create([
            'user_id' => $applicant->user_id,
            'service_id' => $service->service_id,
            'form_id' => $form->service_application_form_id,
            'status' => ServiceApplication::STATUS_PENDING,
            'snapshot' => ['message' => 'Please'],
            'submitted_at' => now(),
        ]);
    }

    private function pendingChurchApplication(string $name = 'Hub Church Lead'): ChurchApplication
    {
        return ChurchApplication::create([
            'requested_name' => $name,
            'requested_short_name' => 'Hub',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Contact',
            'contact_email' => 'hub-church@example.com',
            'contact_mobile' => '01001112222',
            'status' => ChurchApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_hub(): void
    {
        $this->get(route('admin.applications-hub.index'))->assertRedirect();
    }

    public function test_plain_user_cannot_access_hub(): void
    {
        $user = $this->createUser(['email' => 'hub-plain@example.com']);

        $this->actingAs($user)
            ->get(route('admin.applications-hub.index'))
            ->assertForbidden();
    }

    public function test_course_reviewer_sees_course_rows_not_church(): void
    {
        $courseApp = $this->pendingCourseApplication('Visible Hub Course');
        $churchApp = $this->pendingChurchApplication('Secret Church Lead');

        $reviewer = $this->createUser(['email' => 'hub-course-reviewer@example.com']);
        $this->grantSystemPermission($reviewer, 'course_application.review');

        $this->actingAs($reviewer)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee('Visible Hub Course', false)
            ->assertSee(route('admin.course-applications.show', $courseApp), false)
            ->assertDontSee('Secret Church Lead', false)
            ->assertDontSee(route('superadmin.church-applications.show', $churchApp), false);
    }

    public function test_service_reviewer_sees_service_rows_not_church(): void
    {
        $serviceApp = $this->pendingServiceApplication('Visible Hub Service');
        $this->pendingChurchApplication('Hidden Church Lead');

        $reviewer = $this->createUser(['email' => 'hub-service-reviewer@example.com']);
        $this->grantSystemPermission($reviewer, 'service_application.review');

        $this->actingAs($reviewer)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee('Visible Hub Service', false)
            ->assertSee(route('admin.service-applications.show', $serviceApp), false)
            ->assertDontSee('Hidden Church Lead', false);
    }

    public function test_superadmin_sees_all_three_types(): void
    {
        $courseApp = $this->pendingCourseApplication('Super Course');
        $serviceApp = $this->pendingServiceApplication('Super Service');
        $churchApp = $this->pendingChurchApplication('Super Church Lead');

        $super = $this->createUser([
            'email' => 'hub-super@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee('Super Course', false)
            ->assertSee('Super Service', false)
            ->assertSee('Super Church Lead', false)
            ->assertSee(route('admin.course-applications.show', $courseApp), false)
            ->assertSee(route('admin.service-applications.show', $serviceApp), false)
            ->assertSee(route('superadmin.church-applications.show', $churchApp), false);
    }

    public function test_type_filter_limits_rows(): void
    {
        $courseApp = $this->pendingCourseApplication('Filter Course Only');
        $serviceApp = $this->pendingServiceApplication('Filter Service Only');

        $super = $this->createUser([
            'email' => 'hub-filter@example.com',
            'is_superadmin' => true,
        ]);

        // Assert via deep-link URLs — service titles also appear in the nav switcher.
        $this->actingAs($super)
            ->get(route('admin.applications-hub.index', ['type' => 'course']))
            ->assertOk()
            ->assertSee(route('admin.course-applications.show', $courseApp), false)
            ->assertDontSee(route('admin.service-applications.show', $serviceApp), false);
    }

    public function test_church_admin_style_user_without_review_perms_cannot_see_church_via_hub(): void
    {
        // Church-admin membership alone must not unlock ChurchApplication rows on the hub.
        $this->pendingChurchApplication('Church Admin Must Not See');

        $user = $this->createUser(['email' => 'hub-church-admin-only@example.com']);
        $this->grantSystemPermission($user, 'church.members.manage');

        $this->actingAs($user)
            ->get(route('admin.applications-hub.index'))
            ->assertForbidden();
    }
}
