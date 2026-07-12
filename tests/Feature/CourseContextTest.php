<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Session;
use App\Services\CourseContextService;
use Tests\Support\EventModuleTestCase;

class CourseContextTest extends EventModuleTestCase
{
    public function test_student_with_two_active_courses_is_redirected_to_picker_on_login(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-two-courses@example.com']);

        $courseA = $this->createCourse(['title' => 'Alpha Course', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Beta Course', 'status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('courses.select'));
    }

    public function test_single_selectable_course_is_auto_selected_on_login(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-one-course@example.com']);
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $course, $studentRole);

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(
            $course->course_id,
            session(CourseContextService::SESSION_KEY)
        );
    }

    public function test_closed_course_is_excluded_from_picker(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-closed@example.com']);

        $active = $this->createCourse(['title' => 'Active Only', 'status' => Course::STATUS_ACTIVE]);
        $closed = $this->createCourse(['title' => 'Closed Course', 'status' => Course::STATUS_CLOSED]);

        $this->assignCourseRole($student, $active, $studentRole);
        $this->assignCourseRole($student, $closed, $studentRole);

        $this->actingAs($student)
            ->get(route('courses.select'))
            ->assertOk()
            ->assertSee('Active Only', false)
            ->assertDontSee('Closed Course', false);
    }

    public function test_superadmin_skips_course_picker(): void
    {
        $super = $this->createUser([
            'email' => 'ctx-super@example.com',
            'is_superadmin' => true,
        ]);

        $this->post(route('login'), [
            'email' => $super->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertNull(session(CourseContextService::SESSION_KEY));

        $this->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('app.name'), false);
    }

    public function test_switching_course_updates_session_and_scopes_sessions_index(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-switch@example.com']);

        $courseA = $this->createCourse(['title' => 'Course A', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Course B', 'status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        Session::create([
            'course_id' => $courseA->course_id,
            'session_title' => 'Session A1',
            'session_date' => now(),
        ]);
        Session::create([
            'course_id' => $courseB->course_id,
            'session_title' => 'Session B1',
            'session_date' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('courses.select.store'), ['course_id' => $courseA->course_id])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($student)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Session A1', false)
            ->assertDontSee('Session B1', false);

        $this->actingAs($student)
            ->post(route('courses.select.store'), ['course_id' => $courseB->course_id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($courseB->course_id, session(CourseContextService::SESSION_KEY));

        $this->actingAs($student)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('Session B1', false)
            ->assertDontSee('Session A1', false);
    }

    public function test_nav_shows_localized_course_title_per_locale(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-locale@example.com']);

        $course = $this->createCourse([
            'title' => 'Fallback Title',
            'title_ar' => 'عنوان عربي',
            'title_en' => 'English Title',
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->assignCourseRole($student, $course, $studentRole);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('locale.switch', 'en'))
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('English Title', false);

        $this->actingAs($student)
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('عنوان عربي', false);
    }

    public function test_branding_css_vars_are_injected_when_theme_is_set(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-brand@example.com']);

        $course = $this->createCourse([
            'status' => Course::STATUS_ACTIVE,
            'branding_theme' => [
                'primary' => '#112233',
                'accent' => '#aabbcc',
            ],
        ]);

        $this->assignCourseRole($student, $course, $studentRole);
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-primary: #112233;', false)
            ->assertSee('--color-accent: #aabbcc;', false);
    }

    public function test_middleware_redirects_to_picker_without_course_context(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-middleware@example.com']);

        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $response = $this->actingAs($student)
            ->get(route('sessions.index'));

        $response->assertRedirect();
        $this->assertStringContainsString(
            route('courses.select'),
            $response->headers->get('Location') ?? ''
        );
    }

    public function test_archived_course_is_excluded_from_picker(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-archived@example.com']);

        $active = $this->createCourse(['title' => 'Active Course', 'status' => Course::STATUS_ACTIVE]);
        $archived = $this->createCourse(['title' => 'Archived Course', 'status' => Course::STATUS_ARCHIVED]);

        $this->assignCourseRole($student, $active, $studentRole);
        $this->assignCourseRole($student, $archived, $studentRole);

        $this->actingAs($student)
            ->get(route('courses.select'))
            ->assertOk()
            ->assertSee('Active Course', false)
            ->assertDontSee('Archived Course', false);
    }

    public function test_multi_course_staff_can_access_system_admin_without_picker(): void
    {
        $adminRole = $this->createRole('admin');
        $staff = $this->createUser(['email' => 'ctx-staff-admin@example.com']);

        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($staff, $courseA, $adminRole);
        $this->assignCourseRole($staff, $courseB, $adminRole);

        $this->actingAs($staff)
            ->get(route('admin.translations.index'))
            ->assertOk();
    }

    public function test_multi_course_student_can_browse_available_courses_without_picker(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-available@example.com']);

        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $this->actingAs($student)
            ->get(route('available-courses.index'))
            ->assertOk();
    }

    public function test_deep_link_to_course_route_syncs_session_context(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-deeplink@example.com']);

        $courseA = $this->createCourse(['title' => 'Deep A', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Deep B', 'status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $this->actingAs($student)
            ->get(route('curriculum.show', $courseB->course_id))
            ->assertOk();

        $this->assertSame($courseB->course_id, session(CourseContextService::SESSION_KEY));
    }

    public function test_roster_defaults_to_current_course_context(): void
    {
        $instructorRole = $this->courseRoleWithPermissions(
            $this->createCourse(['title' => 'Roster A', 'status' => Course::STATUS_ACTIVE]),
            'instructor-a',
            ['roster.view']
        );
        $courseA = Course::find($instructorRole->course_id);
        $courseB = $this->createCourse(['title' => 'Roster B', 'status' => Course::STATUS_ACTIVE]);
        $instructorRoleB = $this->courseRoleWithPermissions($courseB, 'instructor-b', ['roster.view']);

        $instructor = $this->createUser(['email' => 'ctx-roster@example.com']);
        $this->assignCourseRole($instructor, $courseA, $instructorRole);
        $this->assignCourseRole($instructor, $courseB, $instructorRoleB);

        $studentA = $this->createUser(['email' => 'ctx-roster-student-a@example.com', 'first_name' => 'AlphaStudent']);
        $studentB = $this->createUser(['email' => 'ctx-roster-student-b@example.com', 'first_name' => 'BetaStudent']);
        $studentRole = $this->createRole('Student');

        $this->assignCourseRole($studentA, $courseA, $studentRole);
        $this->assignCourseRole($studentB, $courseB, $studentRole);

        app(CourseContextService::class)->setCurrentCourse($instructor, $courseA->course_id);

        $this->actingAs($instructor)
            ->get(route('students.roster'))
            ->assertOk()
            ->assertSee('AlphaStudent', false)
            ->assertDontSee('BetaStudent', false);
    }

    public function test_exams_index_is_scoped_to_current_course(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-exams@example.com']);

        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        \App\Models\Exam::create([
            'course_id' => $courseA->course_id,
            'exam_name' => 'Exam Alpha',
            'exam_type' => 'exam',
            'delivery_mode' => 'online',
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        \App\Models\Exam::create([
            'course_id' => $courseB->course_id,
            'exam_name' => 'Exam Beta',
            'exam_type' => 'exam',
            'delivery_mode' => 'online',
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        app(CourseContextService::class)->setCurrentCourse($student, $courseA->course_id);

        $this->actingAs($student)
            ->get(route('exams.index'))
            ->assertOk()
            ->assertSee('Exam Alpha', false)
            ->assertDontSee('Exam Beta', false);
    }

    public function test_graduation_index_redirects_to_current_course(): void
    {
        $adminRole = $this->createRole('admin');
        $staff = $this->createUser(['email' => 'ctx-graduation@example.com']);

        $courseA = $this->createCourse(['title' => 'Grad A', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Grad B', 'status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($staff, $courseA, $adminRole);
        $this->assignCourseRole($staff, $courseB, $adminRole);

        app(CourseContextService::class)->setCurrentCourse($staff, $courseA->course_id);

        $this->actingAs($staff)
            ->get(route('graduation.index'))
            ->assertRedirect(route('graduation.show', $courseA->course_id));
    }
}
