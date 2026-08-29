<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradebookSyncService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\EventModuleTestCase;

/**
 * UC-PRJ-14 / UC-PRJ-32: announcing is one-shot, so a gradebook problem must
 * never strand the instructor with un-announced results.
 */
class ProjectAnnounceResilienceTest extends EventModuleTestCase
{
    public function test_announce_succeeds_even_when_the_gradebook_sync_throws(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: true);
        app(ProjectGradingService::class)->gradeTeam($assessment, $projects[0], [], $admin, null, 90.0);

        $this->instance(ProjectGradebookSyncService::class, new class extends ProjectGradebookSyncService
        {
            public function sync(ProjectAssessment $assessment, User $actor): array
            {
                throw new RuntimeException('gradebook exploded');
            }
        });
        Log::spy();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $assessment->fresh();
        $this->assertTrue($fresh->areResultsAnnounced());
        $this->assertNull($fresh->gradebook_synced_at);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.results_announced')->exists());
        $this->assertSame(0, GradeItem::query()->count());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'gradebook sync failed'))
            ->once();
    }

    public function test_announce_is_a_no_op_when_the_course_has_no_project_category(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: false);
        app(ProjectGradingService::class)->gradeTeam($assessment, $projects[0], [], $admin, null, 70.0);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($assessment->fresh()->areResultsAnnounced());
        $this->assertNull($assessment->fresh()->gradebook_synced_at);
        $this->assertSame(0, GradeItem::query()->count());
        $this->assertSame(0, StudentGrade::query()->count());
    }

    public function test_a_second_announce_is_a_conflict_and_keeps_the_first_timestamp(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: false);
        app(ProjectGradingService::class)->gradeTeam($assessment, $projects[0], [], $admin, null, 60.0);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)->post(route('projects.grades.announce', $assessment))->assertRedirect();

        $announcedAt = $assessment->fresh()->results_announced_at;

        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment->fresh()))
            ->assertStatus(409);

        $this->assertTrue($announcedAt->equalTo($assessment->fresh()->results_announced_at));
        $this->assertSame(
            1,
            ActivityLog::query()->where('route_name', 'project.results_announced')->count()
        );
    }

    public function test_sync_reports_why_it_skipped(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: true);
        $sync = app(ProjectGradebookSyncService::class);

        $this->assertSame('disabled', $sync->sync($assessment, $admin)['skipped']);

        $assessment->forceFill(['sync_to_gradebook' => true, 'max_points' => 0])->save();
        $this->assertSame('no_max_points', $sync->sync($assessment->fresh(), $admin)['skipped']);

        $assessment->forceFill(['max_points' => 100])->save();
        $result = $sync->sync($assessment->fresh(), $admin);
        $this->assertNull($result['skipped']);
        $this->assertSame(1, GradeItem::query()->count());
    }

    public function test_grade_edits_after_the_announcement_reach_the_gradebook_on_the_next_save(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: true);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 50.0);
        $grading->announce($assessment, $admin);

        $itemId = (int) $assessment->fresh()->gradebook_item_id;
        $grading->gradeTeam($assessment->fresh(), $projects[0], [], $admin, null, 95.0);

        // The grade edit alone does not push; saving the assessment re-syncs it.
        $this->assertSame(
            50.0,
            (float) StudentGrade::query()->where('item_id', $itemId)->firstOrFail()->score
        );

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->put(route('projects.assessments.update', $assessment->fresh()), [
                'title' => $assessment->title,
                'min_team_size' => $assessment->min_team_size,
                'max_team_size' => $assessment->max_team_size,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'sync_to_gradebook' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, GradeItem::query()->count());
        $this->assertSame(
            95.0,
            (float) StudentGrade::query()->where('item_id', $itemId)->firstOrFail()->score
        );
    }

    /**
     * Published assessment (max 100) with two teams and one seated student.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<Project>, 4: list<User>}
     */
    private function fixture(bool $sync, bool $withCategory): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Announce Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Announce Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'announce-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "announce-s{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        if ($withCategory) {
            GradeCategory::create([
                'course_id' => $course->course_id,
                'type' => ProjectGradebookSyncService::CATEGORY_TYPE,
                'name' => 'Projects',
                'weight_percentage' => 20,
                'ordering' => 1,
            ]);
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Announce Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
            'sync_to_gradebook' => $sync,
        ], $admin);
        $assessment->update(['is_published' => true]);

        app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);

        $projects = $assessment->projects()->orderBy('sort_order')->get()->all();

        return [$course, $assessment->fresh('criteria'), $admin, $projects, $students];
    }
}
