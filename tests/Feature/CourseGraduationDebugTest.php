<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\UserCourseRole;
use App\Services\CourseClosingService;
use App\Services\CoursePermissionResolver;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class CourseGraduationDebugTest extends EventModuleTestCase
{
    public function test_instructor_not_assigned_cannot_access_closing(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'other-course-instructor@example.com']);

        $assignedCourse = $this->createCourse(['title' => 'Assigned course']);
        $otherCourse = $this->createCourse(['title' => 'Other course']);
        $otherCourse->update(['passing_percentage' => 60, 'min_attendance_percentage' => 75]);

        $this->assignCourseRole($instructor, $assignedCourse, $instructorRole);

        $this->actingAs($instructor)
            ->get(route('courses.closing.show', $otherCourse->course_id))
            ->assertForbidden();
    }

    public function test_close_does_not_archive_acting_admin_assignment(): void
    {
        Mail::fake();
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $student = $this->createUser();
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $course->update(['passing_percentage' => 60, 'min_attendance_percentage' => 0]);

        $category = GradeCategory::create(['course_id' => $course->course_id, 'type' => 'exam', 'name' => 'Exams', 'weight_percentage' => 100, 'ordering' => 0]);
        $item = GradeItem::create(['category_id' => $category->category_id, 'title' => 'Final', 'max_score' => 100, 'ordering' => 0]);
        StudentGrade::create(['item_id' => $item->item_id, 'user_id' => $student->user_id, 'score' => 80, 'graded_by_id' => $admin->user_id, 'graded_at' => now()]);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);
        $closing->announce($course->fresh(), $admin);
        $closing->close($course->fresh(), $admin, true);

        $assignment = UserCourseRole::where('course_id', $course->course_id)->where('user_id', $admin->user_id)->first();
        $this->assertNull($assignment->staff_archived_at, 'Acting admin assignment should not be archived on close');
    }

    public function test_student_grades_redirect_to_final_grades_after_announce(): void
    {
        Mail::fake();
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $student = $this->createUser();
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $course->update(['passing_percentage' => 60, 'min_attendance_percentage' => 0]);

        $category = GradeCategory::create(['course_id' => $course->course_id, 'type' => 'exam', 'name' => 'Exams', 'weight_percentage' => 100, 'ordering' => 0]);
        $item = GradeItem::create(['category_id' => $category->category_id, 'title' => 'Final', 'max_score' => 100, 'ordering' => 0]);
        StudentGrade::create(['item_id' => $item->item_id, 'user_id' => $student->user_id, 'score' => 80, 'graded_by_id' => $admin->user_id, 'graded_at' => now()]);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);
        $closing->announce($course->fresh(), $admin);

        $this->actingAs($student)
            ->get(route('grades.show', $course->course_id))
            ->assertRedirect(route('courses.final-grades', $course->course_id));
    }

    public function test_archived_staff_loses_manage_permissions_on_close(): void
    {
        Mail::fake();
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $otherInstructor = $this->createUser(['email' => 'archived-instructor@example.com']);
        $student = $this->createUser();
        $course = $this->createCourse();

        $instructorRole = $this->courseRoleWithPermissions($course, 'instructor', ['exam.author', 'curriculum.manage']);

        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($otherInstructor, $course, $instructorRole);
        $course->update(['passing_percentage' => 60, 'min_attendance_percentage' => 0]);

        $category = GradeCategory::create(['course_id' => $course->course_id, 'type' => 'exam', 'name' => 'Exams', 'weight_percentage' => 100, 'ordering' => 0]);
        $item = GradeItem::create(['category_id' => $category->category_id, 'title' => 'Final', 'max_score' => 100, 'ordering' => 0]);
        StudentGrade::create(['item_id' => $item->item_id, 'user_id' => $student->user_id, 'score' => 80, 'graded_by_id' => $admin->user_id, 'graded_at' => now()]);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);
        $closing->announce($course->fresh(), $admin);
        $closing->close($course->fresh(), $admin, true);

        $archived = UserCourseRole::where('course_id', $course->course_id)
            ->where('user_id', $otherInstructor->user_id)
            ->first();

        $this->assertNotNull($archived->staff_archived_at);

        $resolver = app(CoursePermissionResolver::class);
        $this->assertFalse($resolver->canInCourse($otherInstructor, 'exam.author', $course->fresh()));
    }
}
