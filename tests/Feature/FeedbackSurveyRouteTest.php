<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\User;
use Tests\Support\EventModuleTestCase;

class FeedbackSurveyRouteTest extends EventModuleTestCase
{
    public function test_create_survey_page_is_not_captured_by_show_route(): void
    {
        [$instructor] = $this->instructorWithCourse();

        $this->actingAs($instructor)
            ->get(route('feedback.surveys.create'))
            ->assertOk()
            ->assertSee(__('pages.feedback_create_survey'), false)
            ->assertSee(__('pages.feedback_blocking_label'), false)
            ->assertSee(__('pages.feedback_non_blocking_label'), false);
    }

    public function test_instructor_can_create_a_blocking_or_non_blocking_survey(): void
    {
        [$instructor, $course, $module] = $this->instructorWithCourse();

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Blocking leftover survey',
                'is_mandatory' => '1',
            ])
            ->assertRedirect();

        $blocking = FeedbackSurvey::query()->where('title', 'Blocking leftover survey')->first();
        $this->assertNotNull($blocking);
        $this->assertTrue($blocking->blocksModuleResults());

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Optional pulse survey',
                'is_mandatory' => '0',
            ])
            ->assertRedirect();

        $optional = FeedbackSurvey::query()->where('title', 'Optional pulse survey')->first();
        $this->assertNotNull($optional);
        $this->assertFalse($optional->blocksModuleResults());

        $this->actingAs($instructor)
            ->put(route('feedback.surveys.update', $blocking), [
                'title' => $blocking->title,
                'is_mandatory' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($blocking->fresh()->blocksModuleResults());
    }

    /**
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function instructorWithCourse(): array
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'feedback-create-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Feedback Route Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $module = Module::create(['title' => 'Feedback module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        return [$instructor, $course, $module];
    }
}
