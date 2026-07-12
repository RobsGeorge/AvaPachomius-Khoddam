<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Services\CourseContextService;
use Tests\Support\EventModuleTestCase;

class AssignmentCourseContextTest extends EventModuleTestCase
{
    public function test_assignments_index_is_scoped_to_current_course(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'assign-ctx-student@example.com']);

        $courseA = $this->createCourse(['title' => 'Assign Course A', 'status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['title' => 'Assign Course B', 'status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        Assignment::create([
            'course_id' => $courseA->course_id,
            'assignment_name' => 'Homework Alpha',
            'assignment_description' => 'Course A work',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
        ]);
        Assignment::create([
            'course_id' => $courseB->course_id,
            'assignment_name' => 'Homework Beta',
            'assignment_description' => 'Course B work',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($student, $courseA->course_id);

        $this->actingAs($student)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Homework Alpha', false)
            ->assertDontSee('Homework Beta', false);
    }

    public function test_assignment_show_rejects_other_course_when_context_differs(): void
    {
        $studentRole = $this->createRole('Student');
        $student = $this->createUser(['email' => 'assign-ctx-block@example.com']);

        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);

        $this->assignCourseRole($student, $courseA, $studentRole);
        $this->assignCourseRole($student, $courseB, $studentRole);

        $assignmentB = Assignment::create([
            'course_id' => $courseB->course_id,
            'assignment_name' => 'Other course task',
            'assignment_description' => 'Hidden',
            'total_points' => 5,
            'due_date' => now()->addWeek(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($student, $courseA->course_id);

        $this->actingAs($student)
            ->get(route('assignments.show', $assignmentB))
            ->assertNotFound();
    }

    public function test_instructor_creates_assignment_for_current_course(): void
    {
        $course = $this->createCourse(['title' => 'Assign Create Course', 'status' => Course::STATUS_ACTIVE]);
        $instructorRole = $this->courseRoleWithPermissions($course, 'instructor', ['assignment.manage']);
        $instructor = $this->createUser(['email' => 'assign-ctx-instructor@example.com']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('assignments.store'), [
                'course_id' => $course->course_id,
                'assignment_name' => 'New scoped task',
                'assignment_description' => 'Created in context',
                'total_points' => 20,
                'due_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('assignments.index'));

        $this->assertDatabaseHas('assignments', [
            'course_id' => $course->course_id,
            'assignment_name' => 'New scoped task',
        ]);
    }

    public function test_assignment_dashboard_counts_only_current_course(): void
    {
        $courseA = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $courseB = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $instructorRoleA = $this->courseRoleWithPermissions($courseA, 'instructor-a', ['assignment.manage']);
        $instructorRoleB = $this->courseRoleWithPermissions($courseB, 'instructor-b', ['assignment.manage']);

        $instructor = $this->createUser(['email' => 'assign-ctx-dashboard@example.com']);
        $this->assignCourseRole($instructor, $courseA, $instructorRoleA);
        $this->assignCourseRole($instructor, $courseB, $instructorRoleB);

        Assignment::create([
            'course_id' => $courseA->course_id,
            'assignment_name' => 'Dash A1',
            'assignment_description' => 'A',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
        ]);
        Assignment::create([
            'course_id' => $courseA->course_id,
            'assignment_name' => 'Dash A2',
            'assignment_description' => 'A2',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
        ]);
        Assignment::create([
            'course_id' => $courseB->course_id,
            'assignment_name' => 'Dash B1',
            'assignment_description' => 'B',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($instructor, $courseA->course_id);

        $this->actingAs($instructor)
            ->get(route('assignments.dashboard'))
            ->assertOk()
            ->assertSee('Dash A1', false)
            ->assertSee('Dash A2', false)
            ->assertDontSee('Dash B1', false);
    }
}
