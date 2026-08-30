<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\ProjectMembershipEvent;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class ProjectMembershipHistoryTest extends EventModuleTestCase
{
    public function test_join_and_leave_write_visible_history(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $projectA, $projectB] = $this->twoTeamFixture();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();

        $projectA = $assessment->activeMembershipFor((int) $students[0]->user_id)->project;
        $this->assertTrue(
            ProjectMembershipEvent::query()
                ->where('project_id', $projectA->project_id)
                ->where('user_id', $students[0]->user_id)
                ->where('event', ProjectMembershipEvent::EVENT_JOINED)
                ->exists()
        );

        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])->post(route('projects.join', $assessment))->assertRedirect();

        // Seat someone on the other team so leave-and-reassign has a destination.
        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $projectB->project_id,
            'user_id' => $students[2]->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $fromId = (int) $projectA->project_id;
        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.leave', $assessment))->assertRedirect();

        $this->assertTrue(
            ProjectMembershipEvent::query()
                ->where('project_id', $fromId)
                ->where('user_id', $students[0]->user_id)
                ->where('event', ProjectMembershipEvent::EVENT_LEFT)
                ->exists()
        );

        $newProject = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project;
        $this->actingAs($students[0])
            ->get(route('projects.show', $newProject))
            ->assertOk()
            ->assertSee(__('projects.team_history'))
            ->assertSee(__('projects.history_event_joined', [
                'name' => $students[0]->displayName(),
            ]));
    }

    public function test_admin_move_writes_moved_out_and_moved_in(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $projectA, $projectB] = $this->twoTeamFixture();

        $assignments = app(ProjectAssignmentService::class);
        $assignments->assignStudent($assessment, $students[0], notify: false);
        $membership = $assessment->activeMembershipFor((int) $students[0]->user_id);

        $assignments->moveMember($membership, $projectB, $admin);

        $this->assertTrue(
            ProjectMembershipEvent::query()
                ->where('project_id', $projectA->project_id)
                ->where('event', ProjectMembershipEvent::EVENT_MOVED_OUT)
                ->where('user_id', $students[0]->user_id)
                ->exists()
        );
        $this->assertTrue(
            ProjectMembershipEvent::query()
                ->where('project_id', $projectB->project_id)
                ->where('event', ProjectMembershipEvent::EVENT_MOVED_IN)
                ->where('user_id', $students[0]->user_id)
                ->exists()
        );
    }

    public function test_merge_writes_merged_in(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $projectA, $projectB] = $this->twoTeamFixture();
        $assignments = app(ProjectAssignmentService::class);

        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $projectA->project_id,
            'user_id' => $students[0]->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $projectB->project_id,
            'user_id' => $students[1]->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $assignments->mergeTeams($projectA, $projectB, $admin);

        $this->assertTrue(
            ProjectMembershipEvent::query()
                ->where('project_id', $projectB->project_id)
                ->where('user_id', $students[0]->user_id)
                ->where('event', ProjectMembershipEvent::EVENT_MERGED_IN)
                ->exists()
        );
        $this->assertFalse(
            ProjectMembershipEvent::query()
                ->where('project_id', $projectB->project_id)
                ->where('user_id', $students[0]->user_id)
                ->where('event', ProjectMembershipEvent::EVENT_MOVED_IN)
                ->exists()
        );
    }

    public function test_api_show_includes_membership_history(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment] = $this->twoTeamFixture();

        app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $project = $assessment->activeMembershipFor((int) $students[0]->user_id)->project;

        Sanctum::actingAs($students[0]);
        $this->getJson('/api/v1/projects/'.$project->project_id)
            ->assertOk()
            ->assertJsonPath('data.membership_history.0.event', ProjectMembershipEvent::EVENT_JOINED)
            ->assertJsonPath('data.membership_history.0.user_id', (int) $students[0]->user_id);
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: \App\Models\ProjectAssessment, 4: Project, 5: Project}
     */
    private function twoTeamFixture(): array
    {
        $course = $this->createCourse(['title' => 'History Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'History Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.join',
        ]);
        $admin = $this->createUser(['email' => 'history-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $student = $this->createUser([
                'email' => "history-student{$i}@example.com",
                'first_name' => 'Hist',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'History Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 2,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $projects = $assessment->projects()->orderBy('project_id')->get();

        return [$course, $admin, $students, $assessment, $projects[0], $projects[1]];
    }
}
