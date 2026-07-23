<?php

namespace Tests\Feature;

use App\Models\FeedbackSurvey;
use App\Models\Module;
use Tests\Support\EventModuleTestCase;

class CurriculumModuleSurveysTest extends EventModuleTestCase
{
    public function test_curriculum_lists_all_module_surveys_after_feedback_opens(): void
    {
        $roles = $this->seedBasicRoles();
        $instructorRole = $this->createRole('instructor');

        $instructor = $this->createUser(['email' => 'curr-surveys-instructor@example.com']);
        $student = $this->createUser(['email' => 'curr-surveys-student@example.com']);
        $course = $this->createCourse(['title' => 'Survey List Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $roles['student']);

        $module = Module::create(['title' => 'Module Alpha', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        // Non-mandatory so RequireMandatoryFeedback does not redirect away from curriculum.
        FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Open Survey A',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'opened_at' => now(),
        ]);
        FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Open Survey B',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => false,
            'opened_at' => now(),
        ]);
        FeedbackSurvey::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Hidden Draft Survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_DRAFT,
            'is_mandatory' => false,
        ]);

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertSee('Open Survey A')
            ->assertSee('Open Survey B')
            ->assertDontSee('Hidden Draft Survey')
            ->assertDontSee(__('pages.manage_feedback'));

        $this->actingAs($instructor)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertSee('Open Survey A')
            ->assertSee('Open Survey B')
            ->assertSee('Hidden Draft Survey')
            ->assertSee(__('pages.manage_feedback'));
    }

    public function test_staff_with_curriculum_and_feedback_manage_see_hub_before_module_ends(): void
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');

        $instructor = $this->createUser(['email' => 'curr-hub-instructor@example.com']);
        $student = $this->createUser(['email' => 'curr-hub-student@example.com']);
        $course = $this->createCourse(['title' => 'Hub Before End Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Active Module', 'description' => 'Still running']);
        $course->modules()->attach($module->module_id, [
            'status' => 'active',
            'feedback_open' => false,
        ]);

        $this->actingAs($instructor)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertSee(__('pages.manage_feedback'))
            ->assertSee(__('pages.feedback_create_survey'));

        $this->actingAs($instructor)
            ->get(route('curriculum.admin', $course->course_id))
            ->assertOk()
            ->assertSee(__('pages.manage_feedback'))
            ->assertSee(__('pages.feedback_create_survey'));

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertDontSee(__('pages.manage_feedback'))
            ->assertDontSee(__('pages.feedback_create_survey'));
    }
}
