<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchService;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\Permission;
use App\Models\RegistrationApplication;
use App\Models\Role;
use App\Models\ServiceApplication;
use App\Models\ServiceApplicationForm;
use App\Models\User;
use App\Models\UserServiceRole;
use App\Models\UserSystemRole;
use App\Services\ApplicationsHubQuery;
use App\Services\CourseApplicationFormService;
use App\Services\ServiceRoleAssignmentService;
use App\Support\Applications\ApplicationQueueItem;
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

    private function pendingCourseApplication(string $title = 'Hub Course', ?Course $course = null): CourseApplication
    {
        $course ??= $this->createCourse(['title' => $title]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student-'.substr(uniqid(), -8), ['exam.view']);
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

    public function test_plain_user_is_forbidden_not_empty_list(): void
    {
        // Intended: hub is reviewer-gated (403), not a public empty-state page.
        $this->pendingServiceApplication('Plain User Should Not See');
        $user = $this->createUser(['email' => 'hub-plain-forbidden@example.com']);

        $this->actingAs($user)
            ->get(route('admin.applications-hub.index'))
            ->assertForbidden();
    }

    public function test_church_admin_sees_own_church_course_and_service_not_b_or_platform(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);

        $churchA = Church::main();
        $churchB = $this->createChurch([
            'slug' => 'hub-bound-b',
            'name' => 'Hub Bound B',
            'status' => 'active',
        ]);

        TenantContext::set($churchA);
        $courseA = $this->createCourse([
            'title' => 'Bound Course A',
            'church_id' => $churchA->church_id,
        ]);
        $serviceA = $this->createService([
            'title' => 'Bound Service A',
            'title_en' => 'Bound Service A',
            'church_id' => $churchA->church_id,
        ]);
        $appCourseA = $this->pendingCourseApplication('Bound Course A', $courseA);
        $appServiceA = $this->pendingServiceApplication('Bound Service A', $serviceA);

        TenantContext::set($churchB);
        $courseB = $this->createCourse([
            'title' => 'Bound Course B',
            'church_id' => $churchB->church_id,
        ]);
        $serviceB = $this->createService([
            'title' => 'Bound Service B',
            'title_en' => 'Bound Service B',
            'church_id' => $churchB->church_id,
        ]);
        $appCourseB = $this->pendingCourseApplication('Bound Course B', $courseB);
        $appServiceB = $this->pendingServiceApplication('Bound Service B', $serviceB);
        TenantContext::clear();

        $churchLead = $this->pendingChurchApplication('Bound Must Hide Church Lead');
        $regUser = $this->createUser([
            'email' => 'hub-bound-reg@example.com',
            'first_name' => 'BoundRegUniqueName',
        ]);
        $registration = RegistrationApplication::create([
            'user_id' => $regUser->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['first_name' => 'BoundRegUniqueName'],
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $admin = $this->createUser(['email' => 'hub-church-admin-a@example.com']);
        $this->grantSystemPermission($admin, 'course_application.review');
        $this->grantSystemPermission($admin, 'service_application.review');
        $this->attachChurchMember($admin, $churchA);

        $this->actingAs($admin)
            ->call('GET', route('admin.applications-hub.index', [], false), [], [], [], [
                'HTTP_HOST' => $churchA->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.course-applications.show', $appCourseA), false)
            ->assertSee(route('admin.service-applications.show', $appServiceA), false)
            ->assertDontSee(route('admin.course-applications.show', $appCourseB), false)
            ->assertDontSee(route('admin.service-applications.show', $appServiceB), false)
            ->assertDontSee('Bound Must Hide Church Lead', false)
            ->assertDontSee(route('superadmin.church-applications.show', $churchLead), false)
            ->assertDontSee(route('admin.registration-applications.show', $registration), false)
            ->assertSee(__('applications_hub.intro_scoped'), false);
    }

    public function test_superadmin_sees_course_service_church_but_not_registration(): void
    {
        $courseApp = $this->pendingCourseApplication('FourSrc Course');
        $serviceApp = $this->pendingServiceApplication('FourSrc Service');
        $churchApp = $this->pendingChurchApplication('FourSrc Church Lead');
        $regUser = $this->createUser(['email' => 'hub-four-reg@example.com', 'first_name' => 'FourSrcReg']);
        $registration = RegistrationApplication::create([
            'user_id' => $regUser->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['first_name' => 'FourSrcReg'],
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $super = $this->createUser([
            'email' => 'hub-four-super@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($super)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee(route('admin.course-applications.show', $courseApp), false)
            ->assertSee(route('admin.service-applications.show', $serviceApp), false)
            ->assertSee(route('superadmin.church-applications.show', $churchApp), false)
            ->assertDontSee(route('admin.registration-applications.show', $registration), false)
            ->assertSee(__('applications_hub.intro_platform'), false);
    }

    public function test_church_admin_can_approve_service_app_reached_via_hub_link(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);

        $church = Church::main();
        TenantContext::set($church);
        $service = $this->createService([
            'title' => 'Approve Via Hub',
            'title_en' => 'Approve Via Hub',
            'church_id' => $church->church_id,
        ]);
        $app = $this->pendingServiceApplication('Approve Via Hub', $service);
        TenantContext::clear();

        $admin = $this->createUser(['email' => 'hub-approve-svc@example.com']);
        $this->grantSystemPermission($admin, 'service_application.review');
        $this->attachChurchMember($admin, $church);

        $this->actingAs($admin)
            ->call('GET', route('admin.applications-hub.index', [], false), [], [], [], [
                'HTTP_HOST' => $church->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.service-applications.show', $app), false);

        $this->actingAs($admin)
            ->call('POST', route('admin.service-applications.approve', $app, false), [], [], [], [
                'HTTP_HOST' => $church->slug.'.example.test',
            ])
            ->assertRedirect(route('admin.service-applications.index'));

        $this->assertSame(ServiceApplication::STATUS_APPROVED, $app->fresh()->status);

        $this->actingAs($admin)
            ->call('GET', route('admin.applications-hub.index', ['filter' => 'pending_review'], false), [], [], [], [
                'HTTP_HOST' => $church->slug.'.example.test',
            ])
            ->assertOk()
            ->assertDontSee(route('admin.service-applications.show', $app), false);

        $this->actingAs($admin)
            ->call('GET', route('admin.applications-hub.index', ['filter' => 'approved'], false), [], [], [], [
                'HTTP_HOST' => $church->slug.'.example.test',
            ])
            ->assertOk()
            ->assertSee(route('admin.service-applications.show', $app), false);
    }

    public function test_merged_list_orders_by_submitted_at_across_types(): void
    {
        $olderCourse = $this->pendingCourseApplication('Sort Older Course');
        $olderCourse->forceFill(['submitted_at' => now()->subHours(3)])->save();

        $midService = $this->pendingServiceApplication('Sort Mid Service');
        $midService->forceFill(['submitted_at' => now()->subHours(2)])->save();

        $newestChurch = $this->pendingChurchApplication('Sort Newest Church');
        $newestChurch->forceFill(['submitted_at' => now()->subHour()])->save();

        $super = $this->createUser([
            'email' => 'hub-sort@example.com',
            'is_superadmin' => true,
        ]);

        $result = app(ApplicationsHubQuery::class)->paginate($super, null, null, 1);
        $urls = $result['items']->getCollection()->map(fn (ApplicationQueueItem $i) => $i->showUrl)->all();

        $this->assertSame([
            route('superadmin.church-applications.show', $newestChurch),
            route('admin.service-applications.show', $midService),
            route('admin.course-applications.show', $olderCourse),
        ], array_slice($urls, 0, 3));
    }

    public function test_pagination_does_not_duplicate_or_drop_across_pages(): void
    {
        $base = now()->subDays(2);
        $apps = [];
        for ($i = 0; $i < ApplicationsHubQuery::PER_PAGE + 5; $i++) {
            $app = $this->pendingServiceApplication('PageSvc '.$i);
            $app->forceFill(['submitted_at' => $base->copy()->addMinutes($i)])->save();
            $apps[] = $app;
        }

        $super = $this->createUser([
            'email' => 'hub-pages@example.com',
            'is_superadmin' => true,
        ]);

        $page1 = app(ApplicationsHubQuery::class)->paginate($super, 'service', null, 1);
        $page2 = app(ApplicationsHubQuery::class)->paginate($super, 'service', null, 2);

        $urls1 = $page1['items']->getCollection()->map(fn (ApplicationQueueItem $i) => $i->showUrl)->all();
        $urls2 = $page2['items']->getCollection()->map(fn (ApplicationQueueItem $i) => $i->showUrl)->all();

        $this->assertCount(ApplicationsHubQuery::PER_PAGE, $urls1);
        $this->assertCount(5, $urls2);
        $this->assertSame([], array_values(array_intersect($urls1, $urls2)));
        $this->assertCount(
            ApplicationsHubQuery::PER_PAGE + 5,
            array_unique(array_merge($urls1, $urls2))
        );
    }

    public function test_single_type_course_only_renders_without_other_types(): void
    {
        $courseApp = $this->pendingCourseApplication('Solo Course Only');
        $reviewer = $this->createUser(['email' => 'hub-solo-course@example.com']);
        $this->grantSystemPermission($reviewer, 'course_application.review');

        $this->actingAs($reviewer)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee(route('admin.course-applications.show', $courseApp), false)
            ->assertDontSee(__('applications_hub.type_service'), false)
            ->assertDontSee(__('applications_hub.type_church'), false)
            ->assertSee(__('applications_hub.intro_scoped'), false);
    }

    public function test_empty_scoped_queue_shows_church_scoped_empty_copy(): void
    {
        $reviewer = $this->createUser(['email' => 'hub-empty-scoped@example.com']);
        $this->grantSystemPermission($reviewer, 'service_application.review');

        $this->actingAs($reviewer)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee(__('applications_hub.no_applications_scoped'), false);
    }

    public function test_hub_escapes_xss_in_church_and_subject_labels(): void
    {
        $payload = '<script>alert("xss")</script>';
        $churchApp = ChurchApplication::create([
            'requested_name' => $payload,
            'requested_short_name' => 'X',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => $payload,
            'contact_email' => 'xss-hub@example.com',
            'contact_mobile' => '01005556666',
            'status' => ChurchApplication::STATUS_PENDING,
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ]);

        $service = $this->createService([
            'title' => $payload,
            'title_en' => $payload,
        ]);
        $serviceApp = $this->pendingServiceApplication($payload, $service);

        $super = $this->createUser([
            'email' => 'hub-xss@example.com',
            'is_superadmin' => true,
        ]);

        $html = $this->actingAs($super)
            ->get(route('admin.applications-hub.index'))
            ->assertOk()
            ->assertSee(route('superadmin.church-applications.show', $churchApp), false)
            ->assertSee(route('admin.service-applications.show', $serviceApp), false)
            ->getContent();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
