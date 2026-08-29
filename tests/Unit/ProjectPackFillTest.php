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

/**
 * Pack-fill preference rules that the existing suite only covers for the
 * "one started team" case: which started team wins when several are open, and
 * how the seed pool sizes itself from the course roster.
 */
class ProjectPackFillTest extends EventModuleTestCase
{
    public function test_pack_fill_picks_the_fullest_started_team(): void
    {
        [$assessment, $projects, $students] = $this->fixture(projectCount: 3, max: 3);

        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);
        $this->seat($assessment, $projects[1], $students[2]);

        $service = app(ProjectAssignmentService::class);

        // Team one holds two members, team two holds one: the fullest wins.
        $landed = $service->assignStudent($assessment, $students[3], notify: false);
        $this->assertSame((int) $projects[0]->project_id, (int) $landed->project_id);

        // Team one is now full, so the only started team left takes the next student.
        $landed = $service->assignStudent($assessment, $students[4], notify: false);
        $this->assertSame((int) $projects[1]->project_id, (int) $landed->project_id);

        $this->assertSame(0, $projects[2]->fresh()->activeMemberCount());
    }

    public function test_tied_started_teams_are_filled_before_any_empty_team_opens(): void
    {
        [$assessment, $projects, $students] = $this->fixture(projectCount: 3, max: 3);

        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[1], $students[1]);

        $service = app(ProjectAssignmentService::class);
        foreach (array_slice($students, 2, 4) as $student) {
            $service->assignStudent($assessment, $student, notify: false);
        }

        $this->assertSame(3, $projects[0]->fresh()->activeMemberCount());
        $this->assertSame(3, $projects[1]->fresh()->activeMemberCount());
        $this->assertSame(
            0,
            $projects[2]->fresh()->activeMemberCount(),
            'An empty team must stay closed while any started team still has a seat.'
        );
    }

    public function test_the_excluded_team_is_skipped_even_when_it_is_the_fullest(): void
    {
        [$assessment, $projects, $students] = $this->fixture(projectCount: 3, max: 3);

        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);
        $this->seat($assessment, $projects[1], $students[2]);

        $picked = app(ProjectAssignmentService::class)
            ->pickProject($assessment, excludeProjectId: (int) $projects[0]->project_id);

        $this->assertSame((int) $projects[1]->project_id, (int) $picked->project_id);
    }

    public function test_seed_pool_defaults_to_the_teams_the_remaining_roster_needs(): void
    {
        [$assessment, $projects, $students, $course] = $this->fixture(projectCount: 6, max: 3, enrol: 7);

        $service = app(ProjectAssignmentService::class);

        $this->assertSame(7, $service->unassignedStudentCount($assessment));
        $this->assertSame(3, $service->seedPoolSize($assessment), 'ceil(7 / 3) = 3 teams are needed.');

        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);
        $this->seat($assessment, $projects[0], $students[2]);

        $this->assertSame(4, $service->unassignedStudentCount($assessment->fresh()));
        $this->assertSame(2, $service->seedPoolSize($assessment->fresh()), 'ceil(4 / 3) = 2 teams are left to open.');
    }

    public function test_an_admin_override_wins_over_the_roster_based_seed_pool(): void
    {
        [$assessment] = $this->fixture(projectCount: 6, max: 3, enrol: 7);

        $assessment->forceFill(['seed_pool_size' => 1])->save();

        $this->assertSame(1, app(ProjectAssignmentService::class)->seedPoolSize($assessment->fresh()));
    }

    private function seat(ProjectAssessment $assessment, Project $project, User $student): void
    {
        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'user_id' => $student->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @return array{0: ProjectAssessment, 1: list<Project>, 2: list<User>, 3: Course}
     */
    private function fixture(int $projectCount, int $max, int $enrol = 0): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Pack Fill Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Pack Fill Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $admin = $this->createUser(['email' => 'packfill-admin@example.com']);
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Pack Fill Assessment',
            'min_team_size' => 1,
            'max_team_size' => $max,
            'project_count' => $projectCount,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < max($enrol, 6); $i++) {
            $student = $this->createUser(['email' => "packfill-s{$i}@example.com"]);
            if ($i < $enrol) {
                $this->assignCourseRole($student, $course, $studentRole);
            }
            $students[] = $student;
        }

        $projects = $assessment->projects()->orderBy('sort_order')->get()->all();

        return [$assessment->fresh(), $projects, $students, $course];
    }
}
