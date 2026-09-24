<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
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
            ->assertSee(__('pages.feedback_non_blocking_label'), false)
            ->assertSee(__('pages.feedback_anonymous_label'), false)
            ->assertSee(__('pages.feedback_identified_label'), false)
            ->assertSee(__('pages.feedback_blocked_assessment'), false);
    }

    public function test_instructor_can_create_a_blocking_or_non_blocking_survey(): void
    {
        [$instructor, $course, $module] = $this->instructorWithCourse();
        $exam = $this->moduleExam($course, $module, 'Blocking exam');

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Blocking leftover survey',
                'is_mandatory' => '1',
                'is_anonymous' => '1',
                'blocked_assessment' => 'exam:'.$exam->exam_id,
            ])
            ->assertRedirect();

        $blocking = FeedbackSurvey::query()->where('title', 'Blocking leftover survey')->first();
        $this->assertNotNull($blocking);
        $this->assertTrue($blocking->blocksModuleResults());
        $this->assertTrue($blocking->isAnonymous());
        $this->assertSame((int) $exam->exam_id, (int) $blocking->blocks_exam_id);

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Optional pulse survey',
                'is_mandatory' => '0',
                'is_anonymous' => '0',
            ])
            ->assertRedirect();

        $optional = FeedbackSurvey::query()->where('title', 'Optional pulse survey')->first();
        $this->assertNotNull($optional);
        $this->assertFalse($optional->blocksModuleResults());
        $this->assertFalse($optional->isAnonymous());

        $this->actingAs($instructor)
            ->put(route('feedback.surveys.update', $blocking), [
                'title' => $blocking->title,
                'is_mandatory' => '0',
                'is_anonymous' => '1',
            ])
            ->assertRedirect();

        $this->assertFalse($blocking->fresh()->blocksModuleResults());
        $this->assertNull($blocking->fresh()->blocks_exam_id);
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

    private function moduleExam(Course $course, Module $module, string $name): Exam
    {
        return Exam::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'exam_name' => $name,
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => 30,
            'total_points' => 10,
            'passing_score' => 50,
            'is_published' => true,
        ]);
    }
}
