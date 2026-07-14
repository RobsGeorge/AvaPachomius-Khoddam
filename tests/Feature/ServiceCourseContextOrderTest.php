<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Services\CourseContextService;
use App\Services\ServiceContextService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ServiceCourseContextOrderTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('service') || ! Schema::hasColumn('course', 'service_id')) {
            $this->markTestSkipped('Service schema not ready.');
        }
    }

    public function test_login_with_multiple_services_redirects_to_service_picker(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-two-svc@example.com']);

        $serviceA = $this->createService(['title' => 'Service Alpha']);
        $serviceB = $this->createService(['title' => 'Service Beta']);
        $courseA = $this->createCourse([
            'title' => 'Year A',
            'service_id' => $serviceA->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $courseB = $this->createCourse([
            'title' => 'Year B',
            'service_id' => $serviceB->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('services.select'));
    }

    public function test_courses_picker_lists_only_courses_in_current_service(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-scoped-courses@example.com']);

        $serviceA = $this->createService(['title' => 'Scoped A']);
        $serviceB = $this->createService(['title' => 'Scoped B']);
        $courseA = $this->createCourse([
            'title' => 'Only In A',
            'service_id' => $serviceA->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $courseB = $this->createCourse([
            'title' => 'Only In B',
            'service_id' => $serviceB->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        app(ServiceContextService::class)->setCurrentService($student, $serviceA);

        $this->actingAs($student)
            ->get(route('courses.select'))
            ->assertOk()
            ->assertSee('Only In A', false)
            ->assertDontSee('Only In B', false);
    }

    public function test_selecting_course_also_activates_its_service(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-course-sets-svc@example.com']);
        $service = $this->createService(['title' => 'Parent Svc']);
        $course = $this->createCourse([
            'title' => 'Child Year',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->post(route('courses.select.store'), ['course_id' => $course->course_id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($course->course_id, session(CourseContextService::SESSION_KEY));
        $this->assertSame($service->service_id, session(ServiceContextService::SESSION_KEY));
    }

    public function test_single_service_and_course_hides_switchers_and_shows_labels(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'ctx-single-label@example.com']);
        $service = $this->createService(['title' => 'Solo Service']);
        $course = $this->createCourse([
            'title' => 'Solo Course',
            'service_id' => $service->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        app(ServiceContextService::class)->setCurrentService($student, $service);
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Solo Service', false)
            ->assertSee('Solo Course', false)
            ->assertDontSee('aria-label="'.__('service.switch_service').'"', false)
            ->assertDontSee('aria-label="'.__('course_context.switch_course').'"', false);
    }
}
