<?php

namespace Tests\Feature\UseCases\Exams;

use App\Models\Exam;
use App\Models\Module;
use Tests\Support\EventModuleTestCase;

/**
 * Exam create/update must persist total_points (max grade).
 */
class ExamTotalPointsTest extends EventModuleTestCase
{
    public function test_store_persists_total_points(): void
    {
        [$admin, $course, $module] = $this->staffCourseWithModule();

        $this->actingAs($admin)
            ->post(route('exams.store'), [
                'exam_name' => 'Final Offline',
                'exam_type' => Exam::TYPE_EXAM,
                'delivery_mode' => Exam::MODE_OFFLINE,
                'duration_minutes' => 90,
                'total_points' => 25,
                'passing_score' => 50,
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
            ])
            ->assertRedirect(route('exams.dashboard'));

        $exam = Exam::where('exam_name', 'Final Offline')->first();
        $this->assertNotNull($exam);
        $this->assertEquals(25.0, (float) $exam->total_points);
    }

    public function test_store_requires_total_points(): void
    {
        [$admin, $course, $module] = $this->staffCourseWithModule();

        $this->actingAs($admin)
            ->from(route('exams.dashboard'))
            ->post(route('exams.store'), [
                'exam_name' => 'Missing Max',
                'exam_type' => Exam::TYPE_EXAM,
                'delivery_mode' => Exam::MODE_OFFLINE,
                'duration_minutes' => 60,
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('total_points');
    }

    public function test_update_persists_total_points(): void
    {
        [$admin, $course, $module] = $this->staffCourseWithModule();
        $exam = Exam::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'exam_name' => 'Editable Exam',
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_OFFLINE,
            'duration_minutes' => 60,
            'total_points' => 10,
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('exams.update', $exam), [
                'exam_name' => 'Editable Exam',
                'exam_type' => Exam::TYPE_EXAM,
                'delivery_mode' => Exam::MODE_OFFLINE,
                'duration_minutes' => 60,
                'total_points' => 40,
                'course_id' => $course->course_id,
                'module_id' => $module->module_id,
            ])
            ->assertRedirect(route('exams.dashboard'));

        $this->assertEquals(40.0, (float) $exam->fresh()->total_points);
    }

    public function test_dashboard_create_form_includes_total_points_field(): void
    {
        [$admin] = $this->staffCourseWithModule();

        $this->actingAs($admin)
            ->get(route('exams.dashboard'))
            ->assertOk()
            ->assertSee('name="total_points"', false)
            ->assertSee(__('exams.total_points'), false);
    }

    /** @return array{0: \App\Models\User, 1: \App\Models\Course, 2: Module} */
    private function staffCourseWithModule(): array
    {
        $admin = $this->createUser([
            'email' => 'exam-tp-admin@example.com',
            'is_superadmin' => true,
        ]);
        $course = $this->createCourse(['title' => 'Total Points Course']);
        $module = Module::create(['title' => 'Theology Pillar', 'description' => 'Test pillar']);
        $course->modules()->attach($module->module_id);

        return [$admin, $course, $module];
    }
}
