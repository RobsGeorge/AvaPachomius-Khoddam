<?php

namespace Tests\Feature\UseCases\Dashboard;

use App\Models\CourseApplication;
use App\Models\CourseApplicationForm;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Services\DashboardService;
use Tests\Support\EventModuleTestCase;

/**
 * F-01 per-persona dashboard focus panel (UC-CRS-08, TC-DASH-*). The panel is
 * composed from existing data and gated by persona: students see upcoming exams,
 * reviewers see the application queue, and a user with nothing sees no panel.
 */
class DashboardFocusTest extends EventModuleTestCase
{
    public function test_student_sees_an_upcoming_exam_focus_card(): void
    {
        $course = $this->createCourse();
        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'dash-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);

        $exam = Exam::create([
            'course_id' => $course->course_id, 'exam_name' => 'Midterm', 'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE, 'duration_minutes' => 30, 'scheduled_date' => now()->addDays(2),
            'total_points' => 10, 'passing_score' => 5, 'is_published' => true,
        ]);
        ExamSchedule::create([
            'exam_id' => $exam->exam_id, 'scheduled_date' => now()->addDays(2), 'is_completed' => false,
        ]);

        $cards = app(DashboardService::class)->focusCards($student->fresh());
        $keys = array_column($cards, 'key');
        $this->assertContains('upcoming_exams', $keys);

        $this->actingAs($student->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.focus_heading'))
            ->assertSee('Midterm');
    }

    public function test_reviewer_sees_the_application_review_queue_card(): void
    {
        $course = $this->createCourse();
        $form = CourseApplicationForm::create([
            'course_id' => $course->course_id, 'is_enabled' => true, 'title' => 'Apply',
        ]);
        $applicant = $this->createUser(['email' => 'dash-applicant@example.com']);
        CourseApplication::create([
            'user_id' => $applicant->user_id,
            'course_id' => $course->course_id,
            'form_id' => $form->getKey(),
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
            'snapshot' => [],
            'submitted_at' => now(),
        ]);

        // A superadmin can review every course, so the queue card must surface.
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'dash-admin@example.com']);

        $cards = app(DashboardService::class)->focusCards($admin);
        $reviewCard = collect($cards)->firstWhere('key', 'review_queue');

        $this->assertNotNull($reviewCard);
        $this->assertSame(1, $reviewCard['count']);
        $this->assertSame(route('course-applications.index'), $reviewCard['url']);
    }

    public function test_a_user_with_nothing_pending_sees_no_focus_panel(): void
    {
        $user = $this->createUser(['email' => 'dash-empty@example.com']);

        $this->assertSame([], app(DashboardService::class)->focusCards($user));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.focus_heading'));
    }
}
