<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * UC-PRJ-04 / UC-PRJ-08 edge paths: the join window boundary, the legacy
 * null window, and the failure modes of the one self-service team change.
 */
class ProjectJoinWindowAndLeaveTest extends EventModuleTestCase
{
    public function test_join_and_leave_close_the_moment_the_window_deadline_passes(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 2, max: 2);

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();

        // The window shuts at the deadline itself, not only after it.
        $assessment->forceFill(['join_closes_at' => now()])->save();
        $assessment = $assessment->fresh();
        $this->assertFalse($assessment->isJoinWindowOpen());

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasErrors('project');

        $this->asStudent($students[1], $course)
            ->post(route('projects.join', $assessment))
            ->assertSessionHasErrors('project');

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $students[1]->user_id));
        $this->assertFalse($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));
    }

    public function test_a_legacy_assessment_without_a_join_window_stays_open(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 2, max: 2);

        // v1 rows predate the required window and must keep working.
        $assessment->forceFill(['join_closes_at' => null])->save();
        $assessment = $assessment->fresh();
        $this->assertTrue($assessment->isJoinWindowOpen());

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();

        $seated = $assessment->activeMembershipFor((int) $students[0]->user_id);
        $this->assertNotNull($seated);

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertNotSame(
            (int) $seated->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id
        );
    }

    public function test_a_failed_leave_keeps_the_seat_and_the_change_chance(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 1, max: 2);

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();
        $seatedId = (int) $assessment->activeMembershipFor((int) $students[0]->user_id)->project_id;

        // The only team is the one being left, so the reassignment has nowhere to go.
        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasErrors('project');

        $membership = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertNotNull($membership, 'A failed reassignment must roll back and keep the seat.');
        $this->assertSame($seatedId, (int) $membership->project_id);
        $this->assertFalse($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));
        $this->assertFalse(ActivityLog::query()->where('route_name', 'project.member_self_left')->exists());

        // Once the admin opens another team the same student may still spend the chance.
        app(ProjectAdminService::class)->createProject($assessment->fresh(), ['title' => 'Rescue team']);

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertTrue($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));
    }

    public function test_leaving_never_returns_the_student_to_the_team_they_left(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 2, max: 3);
        $projects = $assessment->projects()->orderBy('sort_order')->get();

        // Team one is the fullest, so pack-fill would pick it again if the
        // exclusion did not hold.
        $this->seat($assessment, $projects[0], $students[0]);
        $this->seat($assessment, $projects[0], $students[1]);
        $this->seat($assessment, $projects[1], $students[2]);

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            (int) $projects[1]->project_id,
            (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id
        );
    }

    public function test_a_seated_student_cannot_join_the_same_assessment_twice(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 2, max: 2);

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();
        $seatedId = (int) $assessment->activeMembershipFor((int) $students[0]->user_id)->project_id;

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertSessionHasErrors('project');

        $this->assertSame(
            1,
            ProjectMembership::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->where('user_id', $students[0]->user_id)
                ->where('status', ProjectMembership::STATUS_ACTIVE)
                ->count()
        );
        $this->assertSame($seatedId, (int) $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id)->project_id);
    }

    public function test_a_student_moved_by_an_admin_may_still_leave_once(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 3, max: 2);
        $projects = $assessment->projects()->orderBy('sort_order')->get();

        $this->asStudent($students[0], $course)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();

        $membership = $assessment->activeMembershipFor((int) $students[0]->user_id);
        $target = $projects->firstWhere(fn (Project $p) => (int) $p->project_id !== (int) $membership->project_id);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $target->project_id])
            ->assertSessionHasNoErrors();

        $this->assertFalse($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($assessment->fresh()->hasUsedChangeChance((int) $students[0]->user_id));

        $this->asStudent($students[0], $course)
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasErrors('project');
    }

    public function test_joining_from_another_course_context_is_not_found(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture(projectCount: 2, max: 2);

        $otherCourse = $this->createCourse(['title' => 'Other Project Course', 'status' => Course::STATUS_ACTIVE]);
        $otherRole = $this->courseRoleWithPermissions($otherCourse, 'student', ['project.view', 'project.join']);
        $this->assignCourseRole($students[0], $otherCourse, $otherRole);

        app(CourseContextService::class)->setCurrentCourse($students[0], $otherCourse->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.join', $assessment))
            ->assertNotFound();

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $students[0]->user_id));
    }

    private function asStudent(User $student, Course $course): self
    {
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        return $this->actingAs($student);
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
     * Published assessment with a future join window and three enrolled students.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<User>}
     */
    private function fixture(int $projectCount, int $max): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Join Window Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Join Window Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'window-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $student = $this->createUser([
                'email' => "window-s{$i}@example.com",
                'first_name' => 'Window',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Join Window Assessment',
            'min_team_size' => 1,
            'max_team_size' => $max,
            'project_count' => $projectCount,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        return [$course, $assessment->fresh(), $admin, $students];
    }
}
