<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectAssignmentServiceTest extends EventModuleTestCase
{
    public function test_pack_fill_finishes_started_team_before_opening_empty(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 3, min: 2, max: 2);

        $service = app(ProjectAssignmentService::class);
        $first = $service->assignStudent($assessment, $students[0]);
        $second = $service->assignStudent($assessment, $students[1]);

        $this->assertSame($first->project_id, $second->project_id);
        $this->assertTrue($second->fresh()->isClosed());

        $third = $service->assignStudent($assessment, $students[2]);
        $this->assertNotSame($first->project_id, $third->project_id);
        $this->assertSame(1, $third->activeMemberCount());
    }

    public function test_never_opens_second_empty_while_a_started_team_has_seats(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 3, min: 2, max: 3);

        $service = app(ProjectAssignmentService::class);
        $first = $service->assignStudent($assessment, $students[0]);

        $ids = [];
        foreach (array_slice($students, 1, 2) as $student) {
            $ids[] = $service->assignStudent($assessment, $student)->project_id;
        }

        $this->assertSame([$first->project_id, $first->project_id], $ids);
        $this->assertSame(3, $first->fresh()->activeMemberCount());
    }

    public function test_join_fails_when_every_team_is_full(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 1, min: 1, max: 1);

        $service = app(ProjectAssignmentService::class);
        $service->assignStudent($assessment, $students[0]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assignStudent($assessment, $students[1]);
    }

    public function test_leaving_a_full_team_reopens_it(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 1, min: 1, max: 1);
        $service = app(ProjectAssignmentService::class);
        $project = $service->assignStudent($assessment, $students[0]);
        $this->assertTrue($project->fresh()->isClosed());

        $membership = ProjectMembership::query()
            ->where('project_id', $project->project_id)
            ->where('user_id', $students[0]->user_id)
            ->first();
        $service->leaveTeam($membership, $assessment);

        $this->assertTrue($project->fresh()->isOpen());
        $this->assertSame(0, $project->fresh()->activeMemberCount());
    }

    public function test_seed_pool_bounds_which_empty_teams_can_open(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(
            projectCount: 6,
            min: 1,
            max: 1,
            seedPoolSize: 2,
        );

        $service = app(ProjectAssignmentService::class);
        $allowed = $assessment->projects()
            ->orderBy('sort_order')
            ->take(2)
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $landed = (int) $service->assignStudent($assessment, $students[0])->project_id;

        $this->assertContains(
            $landed,
            $allowed,
            'Pack-fill may only seed an empty team inside the seed pool prefix.'
        );
    }

    public function test_seed_pool_of_one_opens_empty_teams_in_sort_order(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(
            projectCount: 4,
            min: 1,
            max: 1,
            seedPoolSize: 1,
        );

        $service = app(ProjectAssignmentService::class);
        $expected = $assessment->projects()
            ->orderBy('sort_order')
            ->take(3)
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $used = [];
        foreach (array_slice($students, 0, 3) as $student) {
            $used[] = (int) $service->assignStudent($assessment, $student)->project_id;
        }

        $this->assertSame($expected, $used);
    }

    public function test_seed_pool_defaults_to_the_teams_the_unassigned_students_need(): void
    {
        Mail::fake();
        [$assessment] = $this->assessmentWithStudents(projectCount: 8, min: 2, max: 4);

        $service = app(ProjectAssignmentService::class);

        // Nobody is enrolled in the fixture course, so the pool floors at one team.
        $this->assertSame(1, $service->seedPoolSize($assessment));

        $assessment->forceFill(['seed_pool_size' => 3])->save();
        $this->assertSame(3, $service->seedPoolSize($assessment->fresh()));
    }

    public function test_locked_and_cancelled_teams_are_skipped_by_pack_fill(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 3, min: 1, max: 2, seedPoolSize: 3);
        $projects = $assessment->projects()->orderBy('sort_order')->get();
        $actor = $this->createUser(['email' => 'lock-actor@example.com']);

        $service = app(ProjectAssignmentService::class);
        $service->lockTeam($projects[0], $actor);
        $service->cancelTeam($projects[1], $actor);

        $landed = (int) $service->assignStudent($assessment, $students[0])->project_id;
        $this->assertSame((int) $projects[2]->project_id, $landed);

        $service->lockTeam($projects[2], $actor);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assignStudent($assessment, $students[1]);
    }

    public function test_below_minimum_is_flagged_only_after_the_join_window_closes(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 2, min: 3, max: 4, seedPoolSize: 1);
        $service = app(ProjectAssignmentService::class);
        $service->assignStudent($assessment, $students[0]);

        $this->assertSame(0, $service->markBelowMinimumAfterJoinClose($assessment));

        $assessment->forceFill(['join_closes_at' => now()->subMinute()])->save();
        $this->assertSame(1, $service->markBelowMinimumAfterJoinClose($assessment->fresh()));

        $started = $assessment->projects()->where('below_minimum', true)->get();
        $this->assertCount(1, $started);
        $this->assertSame(1, $started->first()->activeMemberCount());
    }

    public function test_self_leave_moves_the_student_off_the_team_they_left(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 2, min: 1, max: 2, seedPoolSize: 1);
        $service = app(ProjectAssignmentService::class);

        $first = $service->assignStudent($assessment, $students[0]);
        $second = $service->leaveAndReassign($assessment, $students[0]);

        $this->assertNotSame((int) $first->project_id, (int) $second->project_id);
        $this->assertTrue($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->leaveAndReassign($assessment->fresh(), $students[0]);
    }

    public function test_merge_is_rejected_when_the_target_has_too_few_seats(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 2, min: 1, max: 2, seedPoolSize: 1);
        $projects = $assessment->projects()->orderBy('sort_order')->get();
        $actor = $this->createUser(['email' => 'merge-actor@example.com']);
        $service = app(ProjectAssignmentService::class);

        $service->assignStudent($assessment, $students[0]);
        $service->assignStudent($assessment, $students[1]);
        $service->assignStudent($assessment, $students[2]);

        // Team one is full (2/2); team two holds the third student.
        $this->assertSame(2, $projects[0]->fresh()->activeMemberCount());
        $this->assertSame(1, $projects[1]->fresh()->activeMemberCount());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->mergeTeams($projects[1]->fresh(), $projects[0]->fresh(), $actor);
    }

    /**
     * @return array{0: ProjectAssessment, 1: list<User>}
     */
    private function assessmentWithStudents(int $projectCount, int $min, int $max, ?int $seedPoolSize = null): array
    {
        $course = $this->createCourse(['title' => 'Pack Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Pack Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $creator = $this->createUser(['email' => 'pack-admin@example.com']);
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Pack Assessment',
            'min_team_size' => $min,
            'max_team_size' => $max,
            'project_count' => $projectCount,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => $seedPoolSize,
        ], $creator);

        $students = [];
        for ($i = 0; $i < 6; $i++) {
            $students[] = $this->createUser(['email' => "pack-s{$i}@example.com"]);
        }

        return [$assessment, $students];
    }
}
