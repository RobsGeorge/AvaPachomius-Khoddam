<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectAssignmentFlowTest extends EventModuleTestCase
{
    public function test_admin_creates_and_publishes_module_linked_assessment(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Ministry Project',
                'min_team_size' => 2,
                'max_team_size' => 3,
                'project_count' => 2,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'requirements' => 'Visit a family',
                'phases' => [
                    ['title' => 'Plan', 'deadline' => now()->addWeek()->format('Y-m-d H:i:s')],
                ],
                'deliverables' => [
                    ['title' => 'Report', 'due_at' => now()->addWeeks(2)->format('Y-m-d H:i:s')],
                ],
            ])
            ->assertRedirect(route('projects.manage'));

        $assessment = ProjectAssessment::query()->where('title', 'Ministry Project')->first();
        $this->assertNotNull($assessment);
        $this->assertSame($module->module_id, $assessment->module_id);
        $this->assertSame(2, $assessment->projects()->count());
        $this->assertSame('Visit a family', $assessment->projects->first()->requirements);
        $this->assertSame(1, $assessment->projects->first()->phases()->count());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.assessment_created')->exists());

        $this->actingAs($admin)
            ->post(route('projects.assessments.publish', $assessment))
            ->assertRedirect();

        $this->assertTrue($assessment->fresh()->is_published);
        $this->assertSame(
            ['Ministry Project 1', 'Ministry Project 2'],
            $assessment->projects()->orderBy('sort_order')->pluck('title')->all()
        );
    }

    public function test_admin_creates_unique_subprojects_and_rejects_duplicates(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Field Visits',
                'min_team_size' => 1,
                'max_team_size' => 3,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    ['title' => 'Elderly home', 'requirements' => 'Visit twice'],
                    ['title' => 'Orphanage'],
                ],
                'requirements' => 'Shared brief',
            ])
            ->assertRedirect(route('projects.manage'));

        $assessment = ProjectAssessment::query()->where('title', 'Field Visits')->first();
        $this->assertNotNull($assessment);
        $titles = $assessment->projects()->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame(['Elderly home', 'Orphanage'], $titles);
        $this->assertSame('Visit twice', $assessment->projects->first()->requirements);
        $this->assertSame('Shared brief', $assessment->projects->last()->requirements);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Dup Project',
                'min_team_size' => 1,
                'max_team_size' => 2,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    ['title' => 'Same topic'],
                    ['title' => 'same topic'],
                ],
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Empty Subs',
                'min_team_size' => 1,
                'max_team_size' => 2,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    ['title' => ''],
                    ['title' => ''],
                ],
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($admin)
            ->post(route('projects.store', $assessment), ['title' => 'Elderly home'])
            ->assertSessionHasErrors('title');

        $this->actingAs($admin)
            ->post(route('projects.store', $assessment), ['title' => 'Street ministry'])
            ->assertRedirect();

        $this->assertTrue($assessment->projects()->where('title', 'Street ministry')->exists());

        $orphanage = $assessment->projects()->where('title', 'Orphanage')->first();
        $this->actingAs($admin)
            ->put(route('projects.update', $orphanage), ['title' => 'elderly home'])
            ->assertSessionHasErrors('title');
    }

    public function test_student_is_assigned_and_first_member_is_notified(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $student = $students[0];
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        $this->actingAs($student)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Ministry Project', false)
            ->assertSee(__('projects.get_assigned'), false);

        $this->actingAs($student)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();

        $membership = $assessment->activeMembershipFor((int) $student->user_id);
        $this->assertNotNull($membership);

        $this->actingAs($student)
            ->get(route('projects.show', $membership->project))
            ->assertOk()
            ->assertSee('Visit a family', false)
            ->assertSee(__('projects.subproject'), false)
            ->assertSee($membership->project->title, false)
            ->assertSee($student->displayName(), false);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('type', 'project_assigned')
                ->exists()
        );
        $notice = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'project_assigned')
            ->first();
        $this->assertTrue((bool) data_get($notice->metadata, 'first_member'));
        $this->assertStringContainsString($membership->project->title, $notice->body);
    }

    public function test_second_member_notifies_joiner_and_existing_teammate(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 1, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();

        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])->post(route('projects.join', $assessment))->assertRedirect();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[1]->user_id)
                ->where('type', 'project_assigned')
                ->where('body', 'like', '%'.$students[0]->displayName().'%')
                ->exists()
        );
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_teammate_joined')
                ->exists()
        );
        $this->assertTrue(
            UserNotification::query()
                ->where('type', 'project_team_completed')
                ->where('user_id', $students[0]->user_id)
                ->exists()
        );
        $this->assertTrue(
            UserNotification::query()
                ->where('type', 'project_team_completed')
                ->where('user_id', $students[1]->user_id)
                ->exists()
        );
    }

    public function test_student_cannot_see_unpublished_or_manage(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->staffWithStudents();
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Hidden Project',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Hidden Project', false);

        $this->actingAs($students[0])
            ->get(route('projects.manage'))
            ->assertForbidden();

        $this->actingAs($students[0])
            ->post(route('projects.join', $assessment))
            ->assertNotFound();
    }

    public function test_student_leaves_once_and_is_immediately_reassigned(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();
        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])->post(route('projects.join', $assessment))->assertRedirect();

        $fromId = (int) $assessment->activeMembershipFor((int) $students[0]->user_id)->project_id;

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.leave', $assessment))
            ->assertRedirect();

        $newMembership = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertNotNull($newMembership);
        $this->assertNotSame($fromId, (int) $newMembership->project_id);
        $this->assertTrue($assessment->hasUsedChangeChance((int) $students[0]->user_id));
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.member_self_left')->exists());

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[1]->user_id)
                ->where('type', 'project_member_left')
                ->exists(),
            'Remaining teammate should be told someone left.'
        );

        $this->actingAs($students[0])
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasErrors('project');
    }

    public function test_join_and_leave_are_blocked_after_the_join_window_closes(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();

        $assessment->forceFill(['join_closes_at' => now()->subMinute()])->save();

        $this->actingAs($students[0])
            ->post(route('projects.leave', $assessment))
            ->assertSessionHasErrors('project');

        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])
            ->post(route('projects.join', $assessment))
            ->assertSessionHasErrors('project');

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $students[1]->user_id));
    }

    public function test_student_change_request_create_path_is_retired(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment));

        $this->actingAs($students[0])
            ->post(route('projects.change-requests.store', $assessment), [
                'reason' => 'Schedule conflict with the other members',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('projects.change_request_retired'));

        $this->assertSame(0, ProjectChangeRequest::query()->count());
    }

    public function test_admin_can_still_decide_legacy_change_requests(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment));
        $fromId = (int) $assessment->activeMembershipFor((int) $students[0]->user_id)->project_id;

        app(ProjectAssignmentService::class)->requestChange($assessment, $students[0], 'Legacy request');

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $change = ProjectChangeRequest::query()->where('user_id', $students[0]->user_id)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('projects.change-requests.approve', $change))
            ->assertRedirect();

        $newMembership = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertNotSame($fromId, (int) $newMembership->project_id);
        $this->assertTrue($assessment->hasUsedChangeChance((int) $students[0]->user_id));
    }

    public function test_admin_moves_a_member_to_another_team(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment));

        $membership = $assessment->activeMembershipFor((int) $students[0]->user_id);
        $target = $assessment->projects()
            ->where('project_id', '!=', $membership->project_id)
            ->firstOrFail();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.members.move', $membership), ['to_project_id' => $target->project_id])
            ->assertRedirect();

        $moved = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertSame((int) $target->project_id, (int) $moved->project_id);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.member_moved')->exists());
        $this->assertFalse(
            $assessment->hasUsedChangeChance((int) $students[0]->user_id),
            'An admin move must not burn the student self-change chance.'
        );
    }

    public function test_admin_locks_cancels_and_merges_teams(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 3, max: 3, min: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();
        $projects = $assessment->projects()->orderBy('sort_order')->get();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.lock', $projects[0]))
            ->assertRedirect();
        $this->assertTrue($projects[0]->fresh()->isLocked());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.team_locked')->exists());

        $this->actingAs($admin)
            ->post(route('projects.cancel', $projects[2]))
            ->assertRedirect();
        $this->assertTrue($projects[2]->fresh()->isCancelled());

        // Locked + cancelled teams are skipped, so the joiner lands on team #2.
        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();
        $seated = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertSame((int) $projects[1]->project_id, (int) $seated->project_id);

        // Under minimum after the window shuts: the manage screen flags it.
        $assessment->forceFill(['join_closes_at' => now()->subMinute()])->save();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)->get(route('projects.manage'))->assertOk();
        $this->assertTrue((bool) $projects[1]->fresh()->below_minimum);

        // Rescue: merge the under-min team into the locked one.
        $this->actingAs($admin)
            ->post(route('projects.merge', $projects[1]), ['into_project_id' => $projects[0]->project_id])
            ->assertRedirect();

        $merged = $assessment->fresh()->activeMembershipFor((int) $students[0]->user_id);
        $this->assertSame((int) $projects[0]->project_id, (int) $merged->project_id);
        $this->assertTrue($projects[1]->fresh()->isCancelled());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.teams_merged')->exists());
    }

    public function test_cancelling_a_team_with_members_is_rejected(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 2, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment));
        $seated = $assessment->activeMembershipFor((int) $students[0]->user_id)->project;

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.cancel', $seated))
            ->assertSessionHasErrors('project');

        $this->assertFalse($seated->fresh()->isCancelled());
    }

    public function test_destroy_writes_audit_and_is_blocked_when_members_exist(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture(projectCount: 1, max: 2);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->first();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment));

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->delete(route('projects.assessments.destroy', $assessment))
            ->assertSessionHasErrors('assessment');

        $empty = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Empty Project',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);

        $this->actingAs($admin)
            ->delete(route('projects.assessments.destroy', $empty))
            ->assertRedirect(route('projects.manage'));

        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.assessment_deleted')->exists());
    }

    /**
     * @return array{0: Course, 1: Module, 2: \App\Models\User}
     */
    private function staffFixture(): array
    {
        $course = $this->createCourse(['title' => 'Project Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Project Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'prj-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $module, $admin];
    }

    /**
     * @return array{0: Course, 1: Module, 2: \App\Models\User, 3: list<\App\Models\User>}
     */
    private function staffWithStudents(int $studentCount = 2): array
    {
        [$course, $module, $admin] = $this->staffFixture();
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < $studentCount; $i++) {
            $student = $this->createUser([
                'email' => "prj-student{$i}@example.com",
                'first_name' => 'Student',
                'second_name' => 'P'.$i,
                'mobile_number' => '010000000'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        return [$course, $module, $admin, $students];
    }

    /**
     * @return array{0: Course, 1: Module, 2: \App\Models\User, 3: list<\App\Models\User>}
     */
    private function publishedFixture(int $projectCount, int $max, int $min = 1): array
    {
        [$course, $module, $admin, $students] = $this->staffWithStudents(3);
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Ministry Project',
            'min_team_size' => $min,
            'max_team_size' => $max,
            'project_count' => $projectCount,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
            'requirements' => 'Visit a family',
            'phases' => [['title' => 'Plan', 'deadline' => now()->addWeek()->toDateTimeString()]],
            'deliverables' => [['title' => 'Report', 'due_at' => now()->addWeeks(2)->toDateTimeString()]],
        ], $admin);
        $assessment->update(['is_published' => true]);

        return [$course, $module, $admin, $students];
    }
}
