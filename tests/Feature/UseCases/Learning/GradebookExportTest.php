<?php

namespace Tests\Feature\UseCases\Learning;

use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * F-08 — gradebook CSV export + enrollment CSV export.
 */
class GradebookExportTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_course_admin_can_export_gradebook_csv(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'gb-admin@example.com']);
        $student = $this->createUser([
            'email' => 'gb-student@example.com',
            'first_name' => 'Mina',
            'second_name' => 'Habib',
            'national_id' => '29901011234567',
        ]);
        $course = $this->createCourse(['title' => 'Gradebook Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $category = GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'exam',
            'name' => 'Exams',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);
        $item = GradeItem::create([
            'category_id' => $category->category_id,
            'title' => 'Final',
            'max_score' => 100,
            'ordering' => 0,
        ]);
        StudentGrade::create([
            'item_id' => $item->item_id,
            'user_id' => $student->user_id,
            'score' => 88,
            'graded_by_id' => $admin->user_id,
            'graded_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('grades.export', $course->course_id));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('national_id', $csv);
        $this->assertStringContainsString('Exams_raw', $csv);
        $this->assertStringContainsString('total', $csv);
        $this->assertStringContainsString('Mina', $csv);
        $this->assertStringContainsString('gb-student@example.com', $csv);
        $this->assertStringContainsString('88', $csv);
    }

    public function test_student_without_grade_manage_cannot_export_gradebook(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'gb-denied@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        $this->actingAs($student)
            ->get(route('grades.export', $course->course_id))
            ->assertForbidden();
    }

    public function test_staff_can_export_course_enrollments_csv(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'enr-admin@example.com']);
        $student = $this->createUser([
            'email' => 'enr-student@example.com',
            'first_name' => 'Sarah',
            'second_name' => 'Nabil',
        ]);
        $course = $this->createCourse(['title' => 'Enrollment Course']);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $response = $this->actingAs($admin)
            ->get(route('students.roster.export', ['course' => $course->course_id]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('course_id', $csv);
        $this->assertStringContainsString('first_name', $csv);
        $this->assertStringContainsString('Sarah', $csv);
        $this->assertStringContainsString('enr-student@example.com', $csv);
        $this->assertStringContainsString((string) $course->course_id, $csv);
    }

    public function test_unrelated_user_cannot_export_enrollments(): void
    {
        $roles = $this->seedBasicRoles();
        $outsider = $this->createUser(['email' => 'enr-out@example.com']);
        $course = $this->createCourse();
        $admin = $this->createUser(['email' => 'enr-course-admin@example.com']);
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $this->actingAs($outsider)
            ->get(route('students.roster.export', ['course' => $course->course_id]))
            ->assertForbidden();
    }
}
