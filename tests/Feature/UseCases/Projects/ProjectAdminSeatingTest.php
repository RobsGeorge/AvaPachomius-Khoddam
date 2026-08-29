<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * UC-PRJ-17..21 error paths: the guards on force-move, lock, cancel/restore and
 * merge, plus the below-minimum flag lifecycle around them.
 */
class ProjectAdminSeatingTest extends EventModuleTestCase
{
    public function test_moving_a_member_to_their_current_team_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[0]->project_id])
            ->assertSessionHasErrors('to_project_id');

        $this->assertSame(
            (int) $projects[0]->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id
        );
    }

    public function test_moving_a_member_into_a_full_team_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);
        $membership = $this->seat($assessment, $projects[1], $students[2]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[0]->project_id])
            ->assertSessionHasErrors('to_project_id');

        $this->assertSame(2, $projects[0]->fresh()->activeMemberCount());
        $this->assertSame(1, $projects[1]->fresh()->activeMemberCount());
    }

    public function test_moving_a_member_into_a_cancelled_team_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.cancel', $projects[2]))
            ->assertSessionHasNoErrors();

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[2]->project_id])
            ->assertSessionHasErrors('to_project_id');

        $this->assertSame(0, $projects[2]->fresh()->activeMemberCount());
    }

    public function test_moving_a_member_into_another_assessments_team_is_not_found(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        $other = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $assessment->module_id,
            'title' => 'Other Seating Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $foreignTeam = $other->projects()->firstOrFail();

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $foreignTeam->project_id])
            ->assertNotFound();

        $this->assertSame(0, $foreignTeam->fresh()->activeMemberCount());
    }

    public function test_a_move_notifies_the_student_and_the_teammates_left_behind(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[1]->project_id])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_member_moved')
                ->exists(),
            'The moved student should be told where they now sit.'
        );
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[1]->user_id)
                ->where('type', 'project_member_left')
                ->exists(),
            'The remaining teammate should be told someone was moved away.'
        );
    }

    public function test_a_student_can_be_seated_again_on_a_team_they_already_left(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[1]->project_id])
            ->assertSessionHasNoErrors();

        $moved = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);

        // Back to the first team: the membership row for that pair is revived,
        // not duplicated (project_memberships is unique on project + user).
        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $moved), ['to_project_id' => $projects[0]->project_id])
            ->assertSessionHasNoErrors();

        $rows = ProjectMembership::query()
            ->where('user_id', $students[0]->user_id)
            ->where('project_id', $projects[0]->project_id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->isActive());
        $this->assertNull($rows->first()->left_at);
        $this->assertSame(1, $projects[0]->fresh()->activeMemberCount());
        $this->assertSame(0, $projects[1]->fresh()->activeMemberCount());
    }

    public function test_a_moved_student_inherits_the_target_teams_grade(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        app(ProjectGradingService::class)->gradeTeam($assessment, $projects[1], [], $admin, null, 80.0);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[1]->project_id])
            ->assertSessionHasNoErrors();

        $grade = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $students[0]->user_id)
            ->firstOrFail();

        $this->assertSame(80.0, (float) $grade->points);
        $this->assertSame(ProjectMemberGrade::SOURCE_TEAM, $grade->source);
        $this->assertSame((int) $projects[1]->project_id, (int) $grade->project_id);
    }

    public function test_a_cancelled_team_can_be_restored_and_takes_students_again(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.cancel', $projects[2]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($projects[2]->fresh()->isCancelled());

        // The same route toggles a cancelled team back on.
        $this->asAdmin($admin, $course)
            ->post(route('projects.cancel', $projects[2]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($projects[2]->fresh()->isCancelled());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.team_restored')->exists());

        // Team one is full and team two is locked, so the restored team is the
        // only seat pack-fill may use.
        $this->asAdmin($admin, $course)
            ->post(route('projects.lock', $projects[1]))
            ->assertSessionHasNoErrors();

        app(CourseContextService::class)->setCurrentCourse($students[2], $course->course_id);
        $this->actingAs($students[2])
            ->post(route('projects.join', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            (int) $projects[2]->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[2]->user_id)->project_id
        );
    }

    public function test_unlocking_a_team_returns_it_to_pack_fill(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();

        foreach ($projects as $project) {
            $this->asAdmin($admin, $course)
                ->post(route('projects.lock', $project))
                ->assertSessionHasNoErrors();
        }

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.join', $assessment))
            ->assertSessionHasErrors('project');

        // The lock route toggles, so posting again unlocks team two.
        $this->asAdmin($admin, $course)
            ->post(route('projects.lock', $projects[1]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($projects[1]->fresh()->isLocked());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.team_unlocked')->exists());

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.join', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            (int) $projects[1]->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id
        );
    }

    public function test_below_minimum_is_recomputed_in_both_directions(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(min: 2, max: 3);
        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[1], $students[1]);
        $this->seat($assessment, $projects[1], $students[2]);

        $assessment->forceFill(['join_closes_at' => now()->subMinute()])->save();
        $this->asAdmin($admin, $course)->get(route('projects.manage'))->assertOk();

        $this->assertTrue((bool) $projects[0]->fresh()->below_minimum);
        $this->assertFalse((bool) $projects[1]->fresh()->below_minimum);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.below_minimum_flagged')->exists());

        // Rescue team one by moving a member across; team two then falls under min.
        $membership = $assessment->fresh()->activeMembershipFor((int) $students[2]->user_id);
        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[0]->project_id])
            ->assertSessionHasNoErrors();

        $this->asAdmin($admin, $course)->get(route('projects.manage'))->assertOk();

        $this->assertFalse((bool) $projects[0]->fresh()->below_minimum);
        $this->assertTrue((bool) $projects[1]->fresh()->below_minimum);
    }

    public function test_cancelling_an_emptied_team_clears_its_below_minimum_flag(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture(min: 2, max: 3);
        $membership = $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[1], $students[1]);
        $this->seat($assessment, $projects[1], $students[2]);

        $assessment->forceFill(['join_closes_at' => now()->subMinute()])->save();
        $this->asAdmin($admin, $course)->get(route('projects.manage'))->assertOk();
        $this->assertTrue((bool) $projects[0]->fresh()->below_minimum);

        $this->asAdmin($admin, $course)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[2]->project_id])
            ->assertSessionHasNoErrors();

        $this->asAdmin($admin, $course)
            ->post(route('projects.cancel', $projects[0]))
            ->assertSessionHasNoErrors();

        $cancelled = $projects[0]->fresh();
        $this->assertTrue($cancelled->isCancelled());
        $this->assertFalse((bool) $cancelled->below_minimum);
    }

    public function test_merging_an_empty_team_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $this->seat($assessment, $projects[1], $students[0]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.merge', $projects[0]), ['into_project_id' => $projects[1]->project_id])
            ->assertSessionHasErrors('from_project_id');

        $this->assertFalse($projects[0]->fresh()->isCancelled());
    }

    public function test_merging_into_a_cancelled_team_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $this->seat($assessment, $projects[0], $students[0]);

        $this->asAdmin($admin, $course)
            ->post(route('projects.cancel', $projects[2]))
            ->assertSessionHasNoErrors();

        $this->asAdmin($admin, $course)
            ->post(route('projects.merge', $projects[0]), ['into_project_id' => $projects[2]->project_id])
            ->assertSessionHasErrors('into_project_id');

        $this->assertSame(1, $projects[0]->fresh()->activeMemberCount());
    }

    public function test_seating_actions_require_the_manage_permission(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $membership = $this->seat($assessment, $projects[0], $students[0]);

        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);

        $this->actingAs($students[1])
            ->post(route('projects.lock', $projects[0]))
            ->assertForbidden();
        $this->actingAs($students[1])
            ->post(route('projects.cancel', $projects[2]))
            ->assertForbidden();
        $this->actingAs($students[1])
            ->post(route('projects.merge', $projects[0]), ['into_project_id' => $projects[1]->project_id])
            ->assertForbidden();
        $this->actingAs($students[1])
            ->post(route('projects.members.move', $membership), ['to_project_id' => $projects[1]->project_id])
            ->assertForbidden();

        $this->assertFalse($projects[0]->fresh()->isLocked());
        $this->assertFalse($projects[2]->fresh()->isCancelled());
        $this->assertSame(
            (int) $projects[0]->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id
        );
    }

    private function asAdmin(User $admin, Course $course): self
    {
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        return $this->actingAs($admin);
    }

    private function seat(ProjectAssessment $assessment, Project $project, User $student): ProjectMembership
    {
        return ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'user_id' => $student->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Published assessment with three teams and four enrolled students.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<Project>, 4: list<User>}
     */
    private function fixture(int $min = 1, int $max = 2): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Seating Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Seating Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'seating-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 4; $i++) {
            $student = $this->createUser([
                'email' => "seating-s{$i}@example.com",
                'first_name' => 'Seating',
                'second_name' => 'S'.$i,
                'mobile_number' => '012000000'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Seating Assessment',
            'min_team_size' => $min,
            'max_team_size' => $max,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 3,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $projects = $assessment->projects()->orderBy('sort_order')->get()->all();

        return [$course, $assessment->fresh(), $admin, $projects, $students];
    }
}
