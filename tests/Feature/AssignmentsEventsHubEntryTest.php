<?php

namespace Tests\Feature;

use App\Services\CourseContextService;
use App\Support\NavigationHub;
use Tests\Support\EventModuleTestCase;

class AssignmentsEventsHubEntryTest extends EventModuleTestCase
{
    public function test_academic_nav_has_single_assignments_and_events_entry(): void
    {
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'hub-instructor', [
            'assignment.view',
            'assignment.manage',
            'events.view',
            'events.reserve',
        ]);
        $user = $this->createUser(['email' => 'hub-nav@example.com']);
        $this->assignCourseRole($user, $course, $role);
        $this->makeEventAdmin($user);

        app(CourseContextService::class)->setCurrentCourse($user, $course->course_id);

        $urls = collect(NavigationHub::academicLinks($user))->pluck('url');

        $this->assertSame(1, $urls->filter(fn ($url) => $url === route('assignments.index'))->count());
        $this->assertFalse($urls->contains(route('assignments.dashboard')));
        $this->assertSame(1, $urls->filter(fn ($url) => $url === route('events.index'))->count());
        $this->assertFalse($urls->contains(route('events.my-reservations')));
        $this->assertFalse($urls->contains(route('events.admin.index')));
    }

    public function test_assignments_hub_shows_manage_tab_for_instructor(): void
    {
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'hub-assign-mgr', ['assignment.view', 'assignment.manage']);
        $instructor = $this->createUser(['email' => 'hub-assign@example.com']);
        $this->assignCourseRole($instructor, $course, $role);
        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee(__('pages.assignments_section_list'), false)
            ->assertSee(__('pages.assignments_section_manage'), false);

        $this->actingAs($instructor)
            ->get(route('assignments.index', ['section' => 'manage']))
            ->assertOk()
            ->assertSee(__('pages.submission_status_report'), false);
    }

    public function test_student_assignments_hub_has_no_manage_tab(): void
    {
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'hub-assign-student', ['assignment.view', 'assignment.submit']);
        $student = $this->createUser(['email' => 'hub-assign-student@example.com']);
        $this->assignCourseRole($student, $course, $role);
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertDontSee(__('pages.assignments_section_manage'), false);
    }

    public function test_events_hub_tabs_for_student_and_admin(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'hub-evt-student@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $this->actingAs($student)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(__('events.section_browse'), false)
            ->assertSee(__('events.section_reservations'), false)
            ->assertDontSee(__('events.section_admin'), false);

        $admin = $this->createUser(['email' => 'hub-evt-admin@example.com']);
        $this->makeEventAdmin($admin);

        $this->actingAs($admin)
            ->get(route('events.index', ['section' => 'admin']))
            ->assertOk()
            ->assertSee(__('events.section_admin'), false)
            ->assertSee(__('events.admin_create'), false);
    }
}
