<?php

namespace Tests\Feature;

use App\Models\UserServiceRole;
use App\Services\CourseRoleAssignmentService;
use App\Services\ServiceRoleAssignmentService;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ServiceLayerTest extends EventModuleTestCase
{
    public function test_default_service_backfill_attaches_new_courses(): void
    {
        $course = $this->createCourse(['title' => 'Bound Course']);

        $this->assertNotNull($course->service_id);
        $this->assertDatabaseHas('service', ['service_id' => $course->service_id]);
    }

    public function test_user_gets_primary_service_membership(): void
    {
        $user = $this->createUser(['email' => 'svc-primary@example.com']);
        $service = $this->createService(['title' => 'Alpha Service']);

        $row = $this->assignServiceRole($user, $service, asPrimary: true);

        $this->assertTrue($row->is_primary);
        $this->assertTrue(app(ServiceRoleAssignmentService::class)->userBelongsToService($user, $service));
    }

    public function test_cross_service_add_for_existing_service_user(): void
    {
        $user = $this->createUser(['email' => 'svc-cross@example.com']);
        $serviceA = $this->createService(['title' => 'Service A']);
        $serviceB = $this->createService(['title' => 'Service B']);

        $this->assignServiceRole($user, $serviceA, asPrimary: true);

        $cross = app(ServiceRoleAssignmentService::class)->addCrossService($user, $serviceB);

        $this->assertSame((int) $serviceB->service_id, (int) $cross->service_id);
        $this->assertFalse($cross->is_primary);
        $this->assertSame(2, UserServiceRole::where('user_id', $user->user_id)->count());
        $this->assertSame(
            (int) $serviceA->service_id,
            (int) UserServiceRole::where('user_id', $user->user_id)->where('is_primary', true)->value('service_id')
        );
    }

    public function test_cannot_assign_second_service_without_cross_flag(): void
    {
        $user = $this->createUser(['email' => 'svc-block@example.com']);
        $serviceA = $this->createService(['title' => 'Block A']);
        $serviceB = $this->createService(['title' => 'Block B']);

        $this->assignServiceRole($user, $serviceA);

        $this->expectException(ValidationException::class);
        $this->assignServiceRole($user, $serviceB, allowCross: false);
    }

    public function test_course_enrollment_requires_service_membership(): void
    {
        $user = $this->createUser(['email' => 'svc-course@example.com']);
        $service = $this->createService(['title' => 'Course Service']);
        $course = $this->createCourse(['title' => 'Year Course', 'service_id' => $service->service_id]);
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        $this->expectException(ValidationException::class);
        app(CourseRoleAssignmentService::class)->assign($user, $course->course_id, $role->role_id, notify: false);
    }

    public function test_course_enrollment_succeeds_after_service_membership(): void
    {
        $user = $this->createUser(['email' => 'svc-enroll@example.com']);
        $service = $this->createService(['title' => 'Enroll Service']);
        $course = $this->createCourse(['title' => 'Enroll Course', 'service_id' => $service->service_id]);
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        $this->assignServiceRole($user, $service);

        $assignment = app(CourseRoleAssignmentService::class)
            ->assign($user, $course->course_id, $role->role_id, notify: false);

        $this->assertSame((int) $course->course_id, (int) $assignment->course_id);
    }

    public function test_superadmin_sees_service_section_in_roles_hub(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-hub@example.com']);
        $this->createService(['title' => 'Hub Service']);

        $this->actingAs($super)
            ->get(route('roles.hub', ['section' => 'service']))
            ->assertOk()
            ->assertSee(__('rbac.section_service'), false)
            ->assertSee(__('service.no_academic_hint'), false);
    }

    public function test_service_admin_permissions_grant_hub_access(): void
    {
        $admin = $this->createUser(['email' => 'svc-admin@example.com']);
        $service = $this->createService(['title' => 'Admin Service']);
        $assigner = app(ServiceRoleAssignmentService::class);
        $role = $assigner->adminRoleFor($service);
        $assigner->assign($admin, $service, $role, asPrimary: true);

        $this->assertTrue($admin->canInService('service.role.manage', $service));
        $this->assertTrue($admin->canInService('service.member.add_cross', $service));
    }

    public function test_service_a_member_cannot_see_service_b_in_context_picker(): void
    {
        $user = $this->createUser(['email' => 'svc-iso@example.com']);
        $serviceA = $this->createService(['title' => 'Isolated A']);
        $serviceB = $this->createService(['title' => 'Isolated B']);
        $this->assignServiceRole($user, $serviceA);

        $selectable = app(\App\Services\ServiceContextService::class)->selectableServices($user);

        $this->assertTrue($selectable->contains('service_id', $serviceA->service_id));
        $this->assertFalse($selectable->contains('service_id', $serviceB->service_id));
    }

    public function test_clone_service_templates_creates_service_roles(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-tpl@example.com']);
        $service = $this->createService(['title' => 'Template Service']);

        $this->actingAs($super)
            ->post(route('services.roles.clone-templates', $service))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'service_id' => $service->service_id,
            'slug' => 'service-admin',
            'is_template' => false,
        ]);
    }

    public function test_service_application_approve_adds_member(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-app-admin@example.com']);
        $applicant = $this->createUser(['email' => 'svc-applicant@example.com']);
        $service = $this->createService(['title' => 'Apply Service']);

        $application = app(\App\Services\ServiceApplicationService::class)
            ->submit($applicant, $service, ['message' => 'Please add me']);

        $this->actingAs($super)
            ->post(route('admin.service-applications.approve', $application))
            ->assertRedirect(route('admin.service-applications.index'));

        $this->assertTrue(
            app(ServiceRoleAssignmentService::class)->userBelongsToService($applicant, $service)
        );
    }

    public function test_service_roster_lists_only_that_service_members(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'svc-roster@example.com']);
        $member = $this->createUser(['email' => 'svc-roster-member@example.com']);
        $other = $this->createUser(['email' => 'svc-roster-other@example.com']);
        $serviceA = $this->createService(['title' => 'Roster A']);
        $serviceB = $this->createService(['title' => 'Roster B']);
        $this->assignServiceRole($member, $serviceA);
        $this->assignServiceRole($other, $serviceB);

        $this->actingAs($super)
            ->get(route('services.roster', ['service' => $serviceA->service_id]))
            ->assertOk()
            ->assertSee($member->email)
            ->assertDontSee($other->email);
    }
}
