<?php

namespace Tests\Feature;

use App\Mail\CourseGraduationMail;
use App\Models\Course;
use App\Models\Exam;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\UserCourseRole;
use App\Services\CourseClosingService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class CourseGraduationClosingTest extends EventModuleTestCase
{
    /** @param array{student: \App\Models\Role, admin: \App\Models\Role} $roles */
    private function setupCourseWithGrades(Course $course, array $roles, $student, $admin): void
    {
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assignCourseRole($admin, $course, $roles['admin']);

        $course->update([
            'passing_percentage' => 60,
            'min_attendance_percentage' => 0,
            'grace_marks_enabled' => true,
            'max_grace_marks' => 5,
        ]);

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
            'score' => 58,
            'graded_by_id' => $admin->user_id,
            'graded_at' => now(),
        ]);
    }

    public function test_lock_grading_blocks_bulk_save(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'grad-close-admin@example.com']);
        $student = $this->createUser(['email' => 'grad-close-student@example.com']);
        $course = $this->createCourse(['title' => 'Closing Test']);
        $this->setupCourseWithGrades($course, $roles, $student, $admin);

        $item = GradeItem::first();
        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);

        $this->actingAs($admin)
            ->post(route('grade-items.scores.save', $item->item_id), [
                'scores' => [$student->user_id => 70],
            ])
            ->assertForbidden();
    }

    public function test_grace_marks_change_preview_eligibility(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $student = $this->createUser();
        $course = $this->createCourse();
        $this->setupCourseWithGrades($course, $roles, $student, $admin);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);

        $enrollment = UserCourseRole::where('course_id', $course->course_id)
            ->where('user_id', $student->user_id)
            ->first();

        $previewBefore = $closing->preview($course->fresh());
        $this->assertFalse($previewBefore->first()['graduated']);

        $closing->updateGraceMarks($course->fresh(), [
            $student->user_id => [
                'eligible_for_grace' => true,
                'pending_grace_marks' => 3,
            ],
        ]);

        $previewAfter = $closing->preview($course->fresh());
        $this->assertTrue($previewAfter->first()['graduated']);
        $this->assertEquals(58.0, $previewAfter->first()['raw_total_grade']);
        $this->assertEquals(3.0, $previewAfter->first()['grace_marks_applied']);

        $liveGrade = $course->fresh(['gradeCategories.items.grades'])->studentTotalGrade($student->user_id);
        $this->assertEquals(58.0, $liveGrade);
    }

    public function test_announce_creates_snapshot_and_student_can_view_final_grades(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['email' => 'grad-announce-admin@example.com']);
        $student = $this->createUser(['email' => 'grad-announce-student@example.com']);
        $course = $this->createCourse();
        $this->setupCourseWithGrades($course, $roles, $student, $admin);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);
        $closing->updateGraceMarks($course->fresh(), [
            $student->user_id => ['eligible_for_grace' => true, 'pending_grace_marks' => 3],
        ]);
        $closing->announce($course->fresh(), $admin);

        $this->actingAs($student)
            ->get(route('courses.final-grades', $course->course_id))
            ->assertOk()
            ->assertSee(__('course_graduation.final_grades_title'));

        Mail::assertSent(CourseGraduationMail::class);
    }

    public function test_student_cannot_view_final_grades_before_announce(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser();
        $admin = $this->createUser();
        $course = $this->createCourse();
        $this->setupCourseWithGrades($course, $roles, $student, $admin);

        $this->actingAs($student)
            ->get(route('courses.final-grades', $course->course_id))
            ->assertForbidden();
    }

    public function test_close_unpublishes_exams(): void
    {
        Mail::fake();

        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $student = $this->createUser();
        $course = $this->createCourse();
        $this->setupCourseWithGrades($course, $roles, $student, $admin);

        $exam = Exam::create([
            'course_id' => $course->course_id,
            'exam_name' => 'Final Exam',
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => 60,
            'is_published' => true,
            'total_points' => 100,
        ]);

        $closing = app(CourseClosingService::class);
        $closing->lockGrading($course->fresh(), $admin);
        $closing->updateGraceMarks($course->fresh(), [
            $student->user_id => ['eligible_for_grace' => true, 'pending_grace_marks' => 5],
        ]);
        $closing->announce($course->fresh(), $admin);
        $closing->close($course->fresh(), $admin);

        $this->assertFalse($exam->fresh()->is_published);
        $this->assertEquals(Course::STATUS_CLOSED, $course->fresh()->status);
    }

    public function test_closing_wizard_accessible_to_admin(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser();
        $course = $this->createCourse();
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $course->update(['passing_percentage' => 60, 'min_attendance_percentage' => 75]);

        $this->actingAs($admin)
            ->get(route('courses.closing.show', $course->course_id))
            ->assertOk()
            ->assertSee(__('course_graduation.closing_title'));
    }
}
