<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Session;
use App\Services\CourseContextService;
use Tests\Support\EventModuleTestCase;

class CurriculumLectureStoreTest extends EventModuleTestCase
{
    public function test_instructor_can_create_lecture_without_a_session(): void
    {
        [$instructor, $student, $course, $module] = $this->seedCurriculumActors();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('lectures.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Orphan Lecture Alpha',
            ])
            ->assertRedirect(route('curriculum.admin', $course->course_id));

        $this->assertDatabaseHas('lectures', [
            'module_id' => $module->module_id,
            'title' => 'Orphan Lecture Alpha',
            'session_id' => null,
        ]);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertSee('Orphan Lecture Alpha', false);
    }

    public function test_session_from_another_course_is_rejected(): void
    {
        [$instructor, , $course, $module] = $this->seedCurriculumActors();

        $other = $this->createCourse(['title' => 'Other Course']);
        $otherModule = Module::create(['title' => 'Other Module', 'description' => 'Desc']);
        $other->modules()->attach($otherModule->module_id);
        $foreignSession = Session::create([
            'course_id' => $other->course_id,
            'module_id' => $otherModule->module_id,
            'week_number' => 1,
            'session_title' => 'Foreign session',
            'session_date' => now()->toDateString(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->postJson(route('lectures.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'session_id' => $foreignSession->session_id,
                'title' => 'Should Fail',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_id');

        $this->assertDatabaseMissing('lectures', ['title' => 'Should Fail']);
    }

    public function test_student_cannot_store_a_lecture(): void
    {
        [, $student, $course, $module] = $this->seedCurriculumActors();

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->post(route('lectures.store'), [
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
                'title' => 'Student Lecture',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('lectures', ['title' => 'Student Lecture']);
    }

    public function test_empty_module_manage_page_includes_lecture_form(): void
    {
        [$instructor, , $course] = $this->seedCurriculumActors();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->get(route('curriculum.admin', $course->course_id))
            ->assertOk()
            ->assertSee(route('lectures.store', absolute: false), false)
            ->assertSee(__('pages.add_new_lecture'), false)
            ->assertSee(__('pages.empty_module_add_lecture_hint'), false)
            ->assertSee(__('pages.add_session'), false)
            ->assertSee('aria-label="'.e(__('pages.manage_exams')).'"', false)
            ->assertDontSee('bi-plus-lg', false);
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\User, 2: \App\Models\Course, 3: Module}
     */
    private function seedCurriculumActors(): array
    {
        $instructor = $this->createUser(['email' => 'lecture-instructor@example.com']);
        $student = $this->createUser(['email' => 'lecture-student@example.com']);
        $course = $this->createCourse(['title' => 'Lecture Store Course']);

        $this->assignCourseRole($instructor, $course, $this->createRole('instructor'));
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $module = Module::create(['title' => 'Empty Module', 'description' => 'No sessions yet']);
        $course->modules()->attach($module->module_id, [
            'status' => 'active',
            'feedback_open' => false,
        ]);

        return [$instructor, $student, $course, $module];
    }
}
