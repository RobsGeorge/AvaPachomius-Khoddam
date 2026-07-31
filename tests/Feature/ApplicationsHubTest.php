<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchService;
use App\Models\ChurchUser;
use App\Models\CourseApplication;
use App\Models\Permission;
use App\Models\RegistrationApplication;
use App\Models\Role;
use App\Models\ServiceApplication;
use App\Models\ServiceApplicationForm;
use App\Models\User;
use App\Models\UserServiceRole;
use App\Models\UserSystemRole;
use App\Services\CourseApplicationFormService;
use App\Services\ServiceRoleAssignmentService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class ApplicationsHubTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
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

    private function grantServicePermission(User $user, ChurchService $service, string $permissionKey): void
    {
        $perm = Permission::where('key', $permissionKey)->firstOrFail();
        $role = Role::create([
            'role_name' => 'Svc '.$permissionKey,
            'role_decription' => $permissionKey,
            'slug' => 'svc-'.str_replace('.', '-', $permissionKey).'-'.$service->service_id.'-'.$user->user_id,
            'service_id' => $service->service_id,
            'church_id' => $service->church_id,
            'is_template' => false,
        ]);
        $role->permissions()->sync([$perm->permission_id]);

        UserServiceRole::create([
            'user_id' => $user->user_id,
            'service_id' => $service->service_id,
            'role_id' => $role->role_id,
            'is_primary' => true,
        ]);
    }

    private function attachChurchMember(User $user, Church $church): void
    {
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
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

    private function pendingServiceApplication(string $title = 'Hub Service', ?ChurchService $service = null): ServiceApplication
    {
        $service ??= $this->createService(['title' => $title, 'title_en' => $title]);
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
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ]);
    }

    private function unverifiedChurchApplication(string $name = 'Unverified Hub Church'): ChurchApplication
    {
        return ChurchApplication::create([
            'requested_name' => $name,
            'requested_short_name' => 'Unverified',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Contact',
            'contact_email' => 'unverified-hub@example.com',
            'contact_mobile' => '01003334444',
            'status' => ChurchApplication::STATUS_UNVERIFIED,
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => null,
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

    public function test_unverified_church_applications_are_hidden_from_hub(): void
    {
        $pending = $this->pendingChurchApplication('Visible Verified Church');
        $unverified = $this->unverifiedChurchApplication('Hidden Unverified Church');

        $super = $this->createUser([
            'email' => 'hub-unverified-filter@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee('Visible Verified Church', false)
            ->assertSee(route('superadmin.church-applications.show', $pending), false)
            ->assertDontSee('Hidden Unverified Church', false)
            ->assertDontSee(route('superadmin.church-applications.show', $unverified), false);
    }

    public function test_registration_applications_never_appear_on_hub(): void
    {
        $applicant = $this->createUser([
            'email' => 'hub-reg-applicant@example.com',
            'first_name' => 'RegHubUniqueFirst',
            'application_status' => RegistrationApplication::STATUS_PENDING_REVIEW,
        ]);
        $registration = RegistrationApplication::create([
            'user_id' => $applicant->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['first_name' => 'RegHubUniqueFirst'],
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $super = $this->createUser([
            'email' => 'hub-reg-super@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertDontSee(route('admin.registration-applications.show', $registration), false);
    }

    public function test_system_service_reviewer_on_church_a_does_not_see_church_b_service_apps(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);

        $churchA = Church::main();
        $churchB = $this->createChurch([
            'slug' => 'hub-svc-b',
            'name' => 'Hub Service B',
            'status' => 'active',
        ]);

        TenantContext::set($churchA);
        $serviceA = $this->createService([
            'title' => 'Hub Isol Service A',
            'title_en' => 'Hub Isol Service A',
            'church_id' => $churchA->church_id,
        ]);
        $appA = $this->pendingServiceApplication('Hub Isol Service A', $serviceA);

        TenantContext::set($churchB);
        $serviceB = $this->createService([
            'title' => 'Hub Isol Service B',
            'title_en' => 'Hub Isol Service B',
            'church_id' => $churchB->church_id,
        ]);
        $appB = $this->pendingServiceApplication('Hub Isol Service B', $serviceB);
        TenantContext::clear();

        $reviewer = $this->createUser(['email' => 'hub-isol-svc-reviewer@example.com']);
        $this->grantSystemPermission($reviewer, 'service_application.review');
        $this->attachChurchMember($reviewer, $churchA);

        $this->actingAs($reviewer)
            ->call('GET', route('admin.applications-hub.index', [], false), [], [], [], [
                'HTTP_HOST' => $churchA->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.service-applications.show', $appA), false)
            ->assertDontSee(route('admin.service-applications.show', $appB), false);

        $this->actingAs($reviewer)
            ->call('GET', route('admin.service-applications.index', [], false), [], [], [], [
                'HTTP_HOST' => $churchA->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.service-applications.show', $appA), false)
            ->assertDontSee(route('admin.service-applications.show', $appB), false);
    }

    public function test_service_scoped_reviewer_sees_only_own_service_not_church(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);

        $church = Church::main();

        TenantContext::set($church);
        $serviceA = $this->createService([
            'title' => 'Scoped Service A',
            'title_en' => 'Scoped Service A',
            'church_id' => $church->church_id,
        ]);
        $serviceB = $this->createService([
            'title' => 'Scoped Service B',
            'title_en' => 'Scoped Service B',
            'church_id' => $church->church_id,
        ]);
        $appA = $this->pendingServiceApplication('Scoped Service A', $serviceA);
        $appB = $this->pendingServiceApplication('Scoped Service B', $serviceB);
        $churchApp = $this->pendingChurchApplication('Scoped Must Hide Church');
        TenantContext::clear();

        $reviewer = $this->createUser(['email' => 'hub-scoped-svc@example.com']);
        $this->grantServicePermission($reviewer, $serviceA, 'service_application.review');
        $this->attachChurchMember($reviewer, $church);

        $this->actingAs($reviewer)
            ->call('GET', route('admin.applications-hub.index', [], false), [], [], [], [
                'HTTP_HOST' => $church->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.service-applications.show', $appA), false)
            ->assertDontSee(route('admin.service-applications.show', $appB), false)
            ->assertDontSee('Scoped Must Hide Church', false)
            ->assertDontSee(route('superadmin.church-applications.show', $churchApp), false);
    }

    public function test_platform_church_applications_perm_unlocks_church_rows_for_superadmin(): void
    {
        // Controllers gate on platform.church_applications (superadmin bypass still applies).
        $churchApp = $this->pendingChurchApplication('Perm Key Church Lead');

        $super = $this->createUser([
            'email' => 'hub-church-perm@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.applications-hub.index', ['type' => 'church']))
            ->assertOk()
            ->assertSee(route('superadmin.church-applications.show', $churchApp), false);
    }
}
