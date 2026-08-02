<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Services\CourseContextService;
use App\Services\ServiceContextService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * After session flush / cache permission recovery, course+service context is gone.
 * A student with a pending mandatory survey must still reach the survey (or the
 * context pickers) — never bounce between middleware redirects.
 */
class MandatoryFeedbackRedirectLoopTest extends EventModuleTestCase
{
    public function test_student_with_pending_mandatory_feedback_and_no_course_context_can_open_home(): void
    {
        [$student, $survey] = $this->studentWithMandatorySurveyAcrossTwoCourses();

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $response = $this->get('/');

        // Must settle on the survey (or an allowed picker), never oscillate.
        $this->assertFalse(
            $this->isRedirectLoop($response, $student, maxHops: 8),
            'Student hit a redirect loop between mandatory feedback and course/service context.'
        );

        $response->assertRedirect(route('feedback.surveys.show', $survey));
        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk();
    }

    public function test_student_can_use_course_picker_while_mandatory_feedback_is_pending(): void
    {
        [$student, $survey] = $this->studentWithMandatorySurveyAcrossTwoCourses();

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->get(route('courses.select'))
            ->assertOk();

        // Survey itself must remain reachable without a selected course.
        $this->get(route('feedback.surveys.show', $survey))
            ->assertOk();
    }

    public function test_student_with_multiple_services_and_mandatory_feedback_does_not_loop(): void
    {
        if (! Schema::hasTable('service') || ! Schema::hasColumn('course', 'service_id')) {
            $this->markTestSkipped('Service schema not ready.');
        }

        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');
        $student = $this->createUser(['email' => 'mandatory-svc-loop@example.com']);
        $instructor = $this->createUser(['email' => 'mandatory-svc-instructor@example.com']);

        $serviceA = $this->createService(['title' => 'Service A']);
        $serviceB = $this->createService(['title' => 'Service B']);
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
        $this->assignCourseRole($instructor, $courseA, $instructorRole);

        $module = Module::create(['title' => 'Mandatory Module', 'description' => 'Desc']);
        $courseA->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        $survey = FeedbackSurvey::create([
            'course_id' => $courseA->course_id,
            'module_id' => $module->module_id,
            'title' => 'Mandatory Service Survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);

        $this->actingAs($student);
        app(ServiceContextService::class)->clearCurrentService();
        app(CourseContextService::class)->clearCurrentCourse();

        $response = $this->get('/dashboard');

        $this->assertFalse(
            $this->isRedirectLoop($response, $student, maxHops: 8),
            'Student hit a redirect loop between mandatory feedback and service context.'
        );

        $this->actingAs($student)
            ->get(route('feedback.surveys.show', $survey))
            ->assertOk();
    }

    /**
     * @return array{0: \App\Models\User, 1: FeedbackSurvey}
     */
    private function studentWithMandatorySurveyAcrossTwoCourses(): array
    {
        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');
        $student = $this->createUser(['email' => 'mandatory-loop-student@example.com']);
        $instructor = $this->createUser(['email' => 'mandatory-loop-instructor@example.com']);

        $courseA = $this->createCourse(['title' => 'Course A', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Course B', 'status' => Course::STATUS_ACTIVE]);
        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);
        $this->assignCourseRole($instructor, $courseA, $instructorRole);

        $module = Module::create(['title' => 'Mandatory Module', 'description' => 'Desc']);
        $courseA->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        $survey = FeedbackSurvey::create([
            'course_id' => $courseA->course_id,
            'module_id' => $module->module_id,
            'title' => 'Mandatory Survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);

        return [$student, $survey];
    }

    private function isRedirectLoop($initialResponse, $user, int $maxHops): bool
    {
        $response = $initialResponse;
        $seen = [];

        for ($i = 0; $i < $maxHops; $i++) {
            if (! $response->isRedirect()) {
                return false;
            }

            $location = (string) $response->headers->get('Location');
            $path = parse_url($location, PHP_URL_PATH) ?: $location;

            if (isset($seen[$path])) {
                return true;
            }
            $seen[$path] = true;

            $response = $this->actingAs($user)->get($path);
        }

        // Still redirecting after max hops ⇒ treat as a loop / unresolved bounce.
        return $response->isRedirect();
    }
}
