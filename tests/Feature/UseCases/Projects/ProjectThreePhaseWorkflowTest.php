<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMemberVerification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use App\Services\ProjectSubmissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectThreePhaseWorkflowTest extends EventModuleTestCase
{
    public function test_adding_a_team_later_copies_the_three_link_slots(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Parish visits',
                'min_team_size' => 2,
                'max_team_size' => 3,
                'project_count' => 1,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'submission_due_at' => now()->addWeeks(5)->toDateTimeString(),
                'subprojects' => [
                    ['title' => 'Original team'],
                ],
            ])
            ->assertRedirect(route('projects.manage'));

        $assessment = ProjectAssessment::query()->where('title', 'Parish visits')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('projects.store', $assessment), ['title' => 'Late team'])
            ->assertSessionHasNoErrors();

        $added = $assessment->projects()->where('title', 'Late team')->firstOrFail();
        $deliverables = $added->deliverables()->orderBy('sort_order')->get();
        $this->assertSame(
            ProjectDeliverable::canonicalSlotKeys(),
            $deliverables->pluck('slot_key')->all()
        );
        $this->assertTrue($deliverables->every(fn (ProjectDeliverable $row) => $row->expectsLink()));

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $student = $this->createUser(['email' => 'late-team-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);
        $assessment->update(['is_published' => true]);
        $original = $assessment->projects()->where('title', 'Original team')->firstOrFail();
        app(ProjectAssignmentService::class)->assignStudent(
            $assessment->fresh(),
            $student,
            excludeProjectId: (int) $original->project_id,
            notify: false,
        );

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)
            ->get(route('projects.show', $added->fresh()))
            ->assertOk()
            ->assertSee('name="link_url"', false)
            ->assertDontSee(__('projects.no_deliverables'), false);
    }

    public function test_backfill_copies_link_slots_onto_teams_created_empty(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();

        $empty = Project::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'title' => 'Empty late team',
            'status' => Project::STATUS_OPEN,
            'sort_order' => 99,
        ]);
        $this->assertSame(0, $empty->deliverables()->count());
        $this->assertSame(0, $empty->phases()->count());

        $filled = app(ProjectAdminService::class)->backfillMissingSharedWorkflow();
        $this->assertGreaterThanOrEqual(1, $filled);

        $empty->refresh()->load(['deliverables', 'phases']);
        $this->assertSame(
            ProjectDeliverable::canonicalSlotKeys(),
            $empty->deliverables->pluck('slot_key')->all()
        );
        $this->assertTrue($empty->deliverables->every(fn (ProjectDeliverable $row) => $row->expectsLink()));
        $this->assertSame($project->phases->count(), $empty->phases->count());
        $this->assertSame((int) $project->church_id, (int) $empty->deliverables->first()->church_id);
    }

    public function test_creating_an_assessment_without_custom_deliverables_seeds_the_three_phases(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Field Ministry',
                'min_team_size' => 2,
                'max_team_size' => 3,
                'project_count' => 1,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'submission_due_at' => now()->addWeeks(5)->toDateTimeString(),
            ])
            ->assertRedirect(route('projects.manage'));

        $assessment = ProjectAssessment::query()->where('title', 'Field Ministry')->first();
        $this->assertNotNull($assessment);
        $this->assertNotNull($assessment->submission_due_at);

        $deliverables = $assessment->projects()->firstOrFail()->deliverables()->orderBy('sort_order')->get();
        $this->assertSame(
            ProjectDeliverable::canonicalSlotKeys(),
            $deliverables->pluck('slot_key')->all()
        );
        $this->assertTrue($deliverables->every(fn (ProjectDeliverable $row) => $row->expectsLink() && $row->is_required));
    }

    public function test_plain_text_is_rejected_and_a_public_link_is_saved_for_the_team(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();
        $announcement = $this->slot($project, ProjectDeliverable::SLOT_ANNOUNCEMENT);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $announcement]), [
                'link_url' => 'not a url',
            ])
            ->assertSessionHasErrors('link_url');

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $announcement]), [
                'link_url' => 'https://example.com/announce.jpg',
                'body' => 'Poster for Sunday.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($students[1])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('https://example.com/announce.jpg', false)
            ->assertSee('Poster for Sunday', false);
    }

    public function test_all_members_must_verify_before_final_submit(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();
        $this->submitAllSlots($project, $students[0]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.final-submit', $project))
            ->assertSessionHasErrors('final');

        $this->actingAs($students[0])
            ->post(route('projects.verify', $project))
            ->assertSessionHasNoErrors();

        $this->actingAs($students[0])
            ->post(route('projects.final-submit', $project))
            ->assertSessionHasErrors('final');

        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])
            ->post(route('projects.verify', $project))
            ->assertSessionHasNoErrors();

        $this->actingAs($students[1])
            ->post(route('projects.final-submit', $project))
            ->assertSessionHasNoErrors();

        $this->assertTrue($project->fresh()->isFinalSubmitted());
        $this->assertSame((int) $students[1]->user_id, (int) $project->fresh()->final_submitted_by_user_id);
        $this->assertSame(2, ProjectMemberVerification::query()->where('project_id', $project->project_id)->count());
    }

    public function test_editing_an_item_clears_verifications_and_the_final_submit(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();
        $this->submitAllSlots($project, $students[0]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.verify', $project));
        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])->post(route('projects.verify', $project));
        $this->actingAs($students[1])->post(route('projects.final-submit', $project));
        $this->assertTrue($project->fresh()->isFinalSubmitted());

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $this->slot($project, ProjectDeliverable::SLOT_FEEDBACK)]), [
                'link_url' => 'https://example.com/quiz-v2',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($project->fresh()->isFinalSubmitted());
        $this->assertSame(0, ProjectMemberVerification::query()->where('project_id', $project->project_id)->count());
    }

    public function test_late_final_submit_caps_the_grade_and_hard_close_blocks_after_grace(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();
        $this->submitAllSlots($project, $students[0]);

        $due = Carbon::now()->addDay();
        $assessment->update(['submission_due_at' => $due]);

        Carbon::setTestNow($due->copy()->addDay());

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.verify', $project))->assertSessionHasNoErrors();
        app(CourseContextService::class)->setCurrentCourse($students[1], $course->course_id);
        $this->actingAs($students[1])->post(route('projects.verify', $project))->assertSessionHasNoErrors();
        $this->actingAs($students[1])->post(route('projects.final-submit', $project))->assertSessionHasNoErrors();

        $this->assertTrue($project->fresh()->isLateFinal());

        $grade = app(ProjectGradingService::class)->gradeTeam(
            $assessment->fresh(),
            $project->fresh(['assessment']),
            [],
            $admin,
            null,
            100.0
        );
        $this->assertEquals(50.0, (float) $grade->points);

        Carbon::setTestNow($due->copy()->addDays(3));
        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $this->slot($project, ProjectDeliverable::SLOT_ANNOUNCEMENT)]), [
                'link_url' => 'https://example.com/too-late',
            ])
            ->assertSessionHasErrors('deliverable');

        Carbon::setTestNow();
    }

    public function test_admin_can_remove_a_member_and_open_the_report(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();
        $this->submitAllSlots($project, $students[0]);
        $membership = $assessment->activeMembershipFor((int) $students[1]->user_id);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.members.remove', $membership))
            ->assertSessionHasNoErrors();

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $students[1]->user_id));
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[1]->user_id)
                ->where('type', 'project_member_removed')
                ->exists()
        );

        $this->actingAs($admin)
            ->get(route('projects.assessments.report', $assessment))
            ->assertOk()
            ->assertSee($project->title, false)
            ->assertSee($students[0]->displayName(), false);
    }

    public function test_join_close_notifies_admins_and_settling_emails_students(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->seatedTeam();

        $assessment->update([
            'join_closes_at' => now()->subHour(),
            'is_published' => true,
        ]);

        $this->artisan('projects:notify-join-closed')->assertSuccessful();

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->user_id)
                ->where('type', 'project_join_window_closed')
                ->exists()
        );
        $this->assertNotNull($assessment->fresh()->join_close_admin_notified_at);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.assessments.settle', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertTrue($assessment->fresh()->areTeamsSettled());
        foreach ($students as $student) {
            $this->assertTrue(
                UserNotification::query()
                    ->where('user_id', $student->user_id)
                    ->where('type', 'project_roster_approved')
                    ->exists()
            );
        }
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: ProjectAssessment, 4: Project}
     */
    private function seatedTeam(): array
    {
        Mail::fake();
        $course = $this->createCourse(['title' => 'Three Phase Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Three Phase Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'phase-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => "phase-student{$i}@example.com",
                'first_name' => 'Phase',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Three Phase Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'submission_due_at' => now()->addWeeks(4)->toDateTimeString(),
            'seed_canonical_slots' => true,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $assignments = app(ProjectAssignmentService::class);
        $project = $assignments->assignStudent($assessment, $students[0], notify: false);
        $assignments->assignStudent($assessment, $students[1], notify: false);

        return [$course, $admin, $students, $assessment->fresh(), $project->fresh(['deliverables', 'assessment'])];
    }

    /**
     * @return array{0: Course, 1: Module, 2: User}
     */
    private function staffFixture(): array
    {
        $course = $this->createCourse(['title' => 'Phase Create Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Phase Create Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'phase-create-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $module, $admin];
    }

    private function slot(Project $project, string $key): ProjectDeliverable
    {
        return $project->deliverables()->where('slot_key', $key)->firstOrFail();
    }

    private function submitAllSlots(Project $project, User $student): void
    {
        $urls = [
            ProjectDeliverable::SLOT_ANNOUNCEMENT => 'https://example.com/announce.jpg',
            ProjectDeliverable::SLOT_MAIN_CONTENT => 'https://example.com/talk.mp4',
            ProjectDeliverable::SLOT_FEEDBACK => 'https://example.com/survey',
        ];

        foreach ($urls as $slot => $url) {
            app(ProjectSubmissionService::class)->submit(
                $project,
                $this->slot($project, $slot),
                $student,
                ['link_url' => $url]
            );
        }
    }
}
