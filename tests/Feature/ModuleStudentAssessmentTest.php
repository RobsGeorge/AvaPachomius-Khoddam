<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssessmentCriterion;
use App\Models\Module;
use App\Models\ModuleStudentAssessment;
use App\Models\StudentInstructorNote;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class ModuleStudentAssessmentTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
        Artisan::call('roles:sync-templates');
    }

    public function test_instructor_can_save_weighted_assessment_after_module_ended(): void
    {
        [$instructor, $student, $course, $module] = $this->makeEndedModuleFixture();

        $this->actingAs($instructor)
            ->get(route('module-assessments.index', [$course, $module]))
            ->assertOk()
            ->assertSee($student->displayName());

        $criteria = AssessmentCriterion::query()->orderBy('order_index')->get();
        $this->assertGreaterThanOrEqual(6, $criteria->count());

        $scores = [];
        foreach ($criteria as $criterion) {
            $scores[$criterion->criterion_id] = 8;
        }

        $this->actingAs($instructor)
            ->put(route('module-assessments.update', [$course, $module, $student]), [
                'scores' => $scores,
                'status' => 'final',
            ])
            ->assertRedirect(route('module-assessments.edit', [$course, $module, $student]));

        $assessment = ModuleStudentAssessment::query()->first();
        $this->assertNotNull($assessment);
        $this->assertSame('final', $assessment->status);
        $this->assertSame(80, $assessment->total_score);
        $this->assertSame(6, $assessment->scores()->count());

        $this->assertTrue(
            ActivityLog::query()->where('route_name', 'module_assessment.saved')->exists()
        );
    }

    public function test_assessment_blocked_when_module_not_ended(): void
    {
        [$instructor, $student, $course, $module] = $this->makeEndedModuleFixture(status: 'active');

        $this->actingAs($instructor)
            ->get(route('module-assessments.index', [$course, $module]))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->get(route('module-assessments.edit', [$course, $module, $student]))
            ->assertForbidden();
    }

    public function test_student_cannot_access_assessments_or_notes(): void
    {
        [$instructor, $student, $course, $module] = $this->makeEndedModuleFixture();

        $this->actingAs($student)
            ->get(route('module-assessments.index', [$course, $module]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('module-assessments.edit', [$course, $module, $student]))
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('module-assessments.update', [$course, $module, $student]), [
                'scores' => [1 => 5],
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('student-notes.store', [$course, $student]), [
                'body' => 'Should not work',
                'module_id' => $module->module_id,
            ])
            ->assertForbidden();

        $this->assertSame(0, ModuleStudentAssessment::query()->count());
        $this->assertSame(0, StudentInstructorNote::query()->count());
        $this->assertNotNull($instructor);
    }

    public function test_notes_are_append_only_and_hide_author_in_ui(): void
    {
        [$instructor, $student, $course, $module] = $this->makeEndedModuleFixture();
        $instructor2 = $this->createUser([
            'email' => 'sma-instructor-2@example.com',
            'first_name' => 'Second',
            'second_name' => 'Assessor',
        ]);
        $this->assignCourseRole($instructor2, $course, $this->createRole('instructor'));

        $this->actingAs($instructor)
            ->post(route('student-notes.store', [$course, $student]), [
                'body' => 'Strong collaborator in week 3.',
                'module_id' => $module->module_id,
                'redirect_module_id' => $module->module_id,
            ])
            ->assertRedirect();

        $this->actingAs($instructor2)
            ->post(route('student-notes.store', [$course, $student]), [
                'body' => 'Needs coaching on punctuality.',
                'module_id' => $module->module_id,
                'redirect_module_id' => $module->module_id,
            ])
            ->assertRedirect();

        $this->assertSame(2, StudentInstructorNote::query()->count());

        $response = $this->actingAs($instructor)
            ->get(route('module-assessments.edit', [$course, $module, $student]));

        $response->assertOk()
            ->assertSee('Strong collaborator in week 3.')
            ->assertSee('Needs coaching on punctuality.')
            ->assertSee(__('pages.instructor_notes_anonymous_hint'));

        $html = $response->getContent();
        $this->assertStringNotContainsString($instructor->displayName(), $html);
        $this->assertStringNotContainsString($instructor2->displayName(), $html);
        $this->assertStringContainsString($student->displayName(), $html);
    }

    public function test_weighted_total_partial_scores_for_draft(): void
    {
        [$instructor, $student, $course, $module] = $this->makeEndedModuleFixture();
        $criteria = AssessmentCriterion::query()->orderBy('order_index')->get();
        $first = $criteria->first();

        $this->actingAs($instructor)
            ->put(route('module-assessments.update', [$course, $module, $student]), [
                'scores' => [$first->criterion_id => 10],
                'status' => 'draft',
            ])
            ->assertRedirect();

        $assessment = ModuleStudentAssessment::query()->first();
        $this->assertSame('draft', $assessment->status);
        $this->assertSame(100, $assessment->total_score);
    }

    /**
     * @return array{0: User, 1: User, 2: \App\Models\Course, 3: Module}
     */
    private function makeEndedModuleFixture(string $status = 'ended'): array
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');

        $instructor = $this->createUser([
            'email' => 'sma-instructor@example.com',
            'first_name' => 'Lead',
            'second_name' => 'Assessor',
        ]);
        $student = $this->createUser([
            'email' => 'sma-student@example.com',
            'first_name' => 'Sam',
            'second_name' => 'Learner',
        ]);
        $course = $this->createCourse(['title' => 'SMA Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'SMA Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => $status,
            'feedback_open' => $status === 'ended',
            'ended_at' => $status === 'ended' ? now() : null,
        ]);

        return [$instructor, $student, $course->fresh(), $module];
    }
}
