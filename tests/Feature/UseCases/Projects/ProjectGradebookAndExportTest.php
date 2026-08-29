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
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectGradebookAndExportTest extends EventModuleTestCase
{
    public function test_announce_pushes_grades_to_the_gradebook_when_sync_is_on(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: true);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 80.0);
        $grading->announce($assessment, $admin);

        $item = GradeItem::query()->where('title', $assessment->title)->firstOrFail();
        $this->assertSame(100.0, (float) $item->max_score);
        $this->assertSame(
            (int) $item->item_id,
            (int) $assessment->fresh()->gradebook_item_id
        );
        $this->assertNotNull($assessment->fresh()->gradebook_synced_at);

        $grade = StudentGrade::query()
            ->where('item_id', $item->item_id)
            ->where('user_id', $students[0]->user_id)
            ->firstOrFail();
        $this->assertSame(80.0, (float) $grade->score);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.gradebook_synced')->exists());
    }

    public function test_announce_leaves_the_gradebook_alone_when_sync_is_off(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: true);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 80.0);
        $grading->announce($assessment, $admin);

        $this->assertSame(0, GradeItem::query()->count());
        $this->assertSame(0, StudentGrade::query()->count());
        $this->assertNull($assessment->fresh()->gradebook_synced_at);
    }

    public function test_sync_is_skipped_when_the_course_has_no_project_category(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: false);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 80.0);
        $result = app(ProjectGradebookSyncService::class)->sync($assessment->fresh(), $admin);

        $this->assertSame('no_category', $result['skipped']);
        $this->assertSame(0, GradeItem::query()->count());
    }

    public function test_resync_updates_the_same_grade_item(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: true);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 60.0);
        $grading->announce($assessment, $admin);

        $itemId = (int) $assessment->fresh()->gradebook_item_id;

        $grading->gradeTeam($assessment->fresh(), $projects[0], [], $admin, null, 90.0);
        app(ProjectGradebookSyncService::class)->sync($assessment->fresh(), $admin);

        $this->assertSame(1, GradeItem::query()->count());
        $this->assertSame($itemId, (int) $assessment->fresh()->gradebook_item_id);
        $this->assertSame(
            90.0,
            (float) StudentGrade::query()
                ->where('item_id', $itemId)
                ->where('user_id', $students[0]->user_id)
                ->firstOrFail()
                ->score
        );
    }

    public function test_turning_sync_on_after_announcing_backfills_the_gradebook(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: true);
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [], $admin, null, 70.0);
        $grading->announce($assessment, $admin);
        $this->assertSame(0, GradeItem::query()->count());

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->put(route('projects.assessments.update', $assessment), [
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
            70.0,
            (float) StudentGrade::query()->where('user_id', $students[0]->user_id)->firstOrFail()->score
        );
    }

    public function test_manage_dashboard_shows_the_assessment_overview(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: true, withCategory: true);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->get(route('projects.manage'))
            ->assertOk()
            ->assertSee(__('projects.overview_seated'))
            ->assertSee(__('projects.overview_teams'))
            ->assertSee(__('projects.overview_graded'))
            ->assertSee(__('projects.export_csv'))
            ->assertSee(__('projects.sync_to_gradebook'));
    }

    public function test_csv_export_streams_one_row_per_member(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: false);
        app(ProjectGradingService::class)->gradeTeam($assessment, $projects[0], [], $admin, null, 75.0);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $response = $this->actingAs($admin)->get(route('projects.export', $assessment));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('team_title', $csv);
        $this->assertStringContainsString('student_points', $csv);
        $this->assertStringContainsString($students[0]->displayName(), $csv);
        $this->assertStringContainsString('75.00', $csv);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.roster_exported')->exists());
    }

    public function test_csv_export_includes_empty_teams(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: false);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $csv = $this->actingAs($admin)
            ->get(route('projects.export', $assessment))
            ->streamedContent();

        foreach ($projects as $project) {
            $this->assertStringContainsString($project->title, $csv);
        }
    }

    public function test_csv_export_requires_the_manage_permission(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(sync: false, withCategory: false);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.export', $assessment))
            ->assertForbidden();
    }

    /**
     * Published assessment (max 100), two teams, one seated student.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<Project>, 4: list<User>}
     */
    private function fixture(bool $sync, bool $withCategory): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Gradebook Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Gradebook Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'gradebook-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => "gradebook-s{$i}@example.com",
                'first_name' => 'Gradebook',
                'second_name' => 'S'.$i,
            ]);
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
            'title' => 'Gradebook Assessment',
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
