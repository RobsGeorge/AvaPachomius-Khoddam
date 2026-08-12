<?php

namespace Tests\Feature\UseCases\Exams;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Services\ExamGradingService;
use Tests\Support\EventModuleTestCase;

/**
 * Offline exam grades UX: points → % conversion, bulk roster save, enrollment checks.
 */
class OfflineExamGradesTest extends EventModuleTestCase
{
    public function test_points_to_percent_helper_converts_correctly(): void
    {
        $exam = $this->offlineExam(['total_points' => 20]);
        $service = app(ExamGradingService::class);

        $this->assertEquals(85.0, $service->pointsToPercent($exam, 17));
        $this->assertEquals(100.0, $service->pointsToPercent($exam, 20));
        $this->assertEquals(0.0, $service->pointsToPercent($exam, 0));
        $this->assertEquals(17.0, $service->percentToPoints($exam, 85));
    }

    public function test_offline_single_grade_accepts_points_and_stores_percentage(): void
    {
        [$admin, $student, $exam, $schedule] = $this->offlineSetup();

        $this->actingAs($admin)
            ->post(route('exams.grades.offline', $exam), [
                'schedule_id' => $schedule->schedule_id,
                'user_id' => $student->user_id,
                'points' => 17,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $result = ExamResult::where('exam_id', $exam->exam_id)
            ->where('user_id', $student->user_id)
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals(85.0, (float) $result->score);
        $this->assertEquals(85.0, (float) $result->manual_score);
        $this->assertSame(ExamResult::STATUS_GRADED, $result->status);
    }

    public function test_offline_bulk_grades_convert_points_to_percentage(): void
    {
        [$admin, $student, $exam, $schedule] = $this->offlineSetup();
        $student2 = $this->createUser(['email' => 'offline-s2@example.com']);
        $roles = $this->seedBasicRoles();
        $this->assignCourseRole($student2, $exam->course, $roles['student']);

        $this->actingAs($admin)
            ->post(route('exams.grades.offline.bulk', $exam), [
                'schedule_id' => $schedule->schedule_id,
                'points' => [
                    $student->user_id => 17,
                    $student2->user_id => 10,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(
            85.0,
            (float) ExamResult::where('user_id', $student->user_id)->where('exam_id', $exam->exam_id)->value('score')
        );
        $this->assertEquals(
            50.0,
            (float) ExamResult::where('user_id', $student2->user_id)->where('exam_id', $exam->exam_id)->value('score')
        );
    }

    public function test_offline_rejects_points_above_total(): void
    {
        [$admin, $student, $exam, $schedule] = $this->offlineSetup();

        $this->actingAs($admin)
            ->from(route('exams.grades', $exam))
            ->post(route('exams.grades.offline', $exam), [
                'schedule_id' => $schedule->schedule_id,
                'user_id' => $student->user_id,
                'points' => 25,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('points');
    }

    public function test_offline_rejects_non_enrolled_student(): void
    {
        [$admin, , $exam, $schedule] = $this->offlineSetup();
        $outsider = $this->createUser(['email' => 'offline-out@example.com']);

        $this->actingAs($admin)
            ->from(route('exams.grades', $exam))
            ->post(route('exams.grades.offline', $exam), [
                'schedule_id' => $schedule->schedule_id,
                'user_id' => $outsider->user_id,
                'points' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $exam->exam_id,
            'user_id' => $outsider->user_id,
        ]);
    }

    public function test_offline_grades_page_lists_enrolled_students_not_user_id_field(): void
    {
        [$admin, $student, $exam] = $this->offlineSetup();

        $response = $this->actingAs($admin)->get(route('exams.grades', $exam));

        $response->assertOk();
        $response->assertSee($student->displayName(), false);
        $response->assertSee(__('exams.points_earned'), false);
        $response->assertSee(__('exams.quick_add_grade'), false);
        $response->assertDontSee('user_id)', false);
        $response->assertSee('name="points['.$student->user_id.']"', false);
    }

    public function test_offline_update_accepts_points_not_percentage(): void
    {
        [$admin, $student, $exam, $schedule] = $this->offlineSetup();

        $result = ExamResult::create([
            'exam_id' => $exam->exam_id,
            'schedule_id' => $schedule->schedule_id,
            'user_id' => $student->user_id,
            'score' => 50,
            'manual_score' => 50,
            'status' => ExamResult::STATUS_GRADED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('exams.grades.update', [$exam, $result]), [
                'points' => 18,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(90.0, (float) $result->fresh()->score);
    }

    public function test_grades_page_can_update_exam_total_points(): void
    {
        [$admin, , $exam] = $this->offlineSetup();
        $exam->update(['total_points' => 0]);

        $this->actingAs($admin)
            ->put(route('exams.grades.total-points', $exam), [
                'total_points' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(20.0, (float) $exam->fresh()->total_points);

        $response = $this->actingAs($admin)->get(route('exams.grades', $exam));
        $response->assertOk();
        $response->assertSee('name="total_points"', false);
        $response->assertSee(__('exams.save_total_points'), false);
    }

    public function test_grades_page_rejects_zero_total_points(): void
    {
        [$admin, , $exam] = $this->offlineSetup();

        $this->actingAs($admin)
            ->from(route('exams.grades', $exam))
            ->put(route('exams.grades.total-points', $exam), [
                'total_points' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('total_points');
    }

    /** @return array{0: \App\Models\User, 1: \App\Models\User, 2: Exam, 3: ExamSchedule} */
    private function offlineSetup(): array
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser([
            'email' => 'offline-admin@example.com',
            'is_superadmin' => true,
        ]);
        $student = $this->createUser([
            'email' => 'offline-stu@example.com',
            'first_name' => 'Sara',
            'second_name' => 'Nabil',
            'third_name' => 'A',
        ]);
        $exam = $this->offlineExam(['total_points' => 20]);
        $this->assignCourseRole($student, $exam->course, $roles['student']);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->exam_id,
            'scheduled_date' => now()->subDay(),
        ]);

        return [$admin, $student, $exam->fresh(['course']), $schedule];
    }

    /** @param array<string,mixed> $overrides */
    private function offlineExam(array $overrides = []): Exam
    {
        $course = $this->createCourse(['title' => 'Offline Exam Course']);

        return Exam::create(array_merge([
            'course_id' => $course->course_id,
            'exam_name' => 'Midterm Offline',
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_OFFLINE,
            'duration_minutes' => 90,
            'scheduled_date' => now()->subDay(),
            'total_points' => 20,
            'passing_score' => 50,
            'is_published' => true,
        ], $overrides));
    }
}
