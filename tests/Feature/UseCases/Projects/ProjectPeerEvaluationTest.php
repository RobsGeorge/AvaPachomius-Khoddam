<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectPeerRating;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectPeerEvaluationService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class ProjectPeerEvaluationTest extends EventModuleTestCase
{
    public function test_student_rates_other_team_anonymously_without_touching_member_grades(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture();
        $this->openPeerEval($assessment);

        $before = ProjectMemberGrade::query()->count();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $teamA), [
                'ratings' => [
                    ['ratee_project_id' => $teamB->project_id, 'score' => 4, 'comment' => 'Solid'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, ProjectPeerRating::query()->count());
        $rating = ProjectPeerRating::query()->firstOrFail();
        $this->assertSame((int) $teamB->project_id, (int) $rating->ratee_project_id);
        $this->assertSame((int) $teamA->project_id, (int) $rating->project_id);
        $this->assertSame($before, ProjectMemberGrade::query()->count());

        $averages = app(ProjectPeerEvaluationService::class)->adminTeamAverages($assessment);
        $row = collect($averages)->firstWhere('project_id', (int) $teamB->project_id);
        $this->assertEqualsWithDelta(4.0, (float) $row['overall_avg'], 0.01);
        $this->assertArrayNotHasKey('rater_user_id', $row);
        $this->assertSame((int) $teamA->project_id, (int) $row['by_rater_team'][0]['project_id']);
        $this->assertArrayNotHasKey('rater_user_id', $row['by_rater_team'][0]);
    }

    public function test_cannot_rate_own_team_or_team_without_submission(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture(withTeamBSubmission: false);
        $this->openPeerEval($assessment);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $teamA), [
                'ratings' => [
                    ['ratee_project_id' => $teamA->project_id, 'score' => 5],
                ],
            ])
            ->assertSessionHasErrors('ratings');

        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $teamA), [
                'ratings' => [
                    ['ratee_project_id' => $teamB->project_id, 'score' => 3],
                ],
            ])
            ->assertSessionHasErrors('ratings');
    }

    public function test_self_pick_respects_max_picks(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB, $teamC] = $this->fixtureThreeTeams();
        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
            'peer_eval_min_picks' => 1,
            'peer_eval_max_picks' => 1,
        ]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $teamA), [
                'ratings' => [
                    ['ratee_project_id' => $teamB->project_id, 'score' => 4],
                    ['ratee_project_id' => $teamC->project_id, 'score' => 5],
                ],
            ])
            ->assertSessionHasErrors('ratings');
    }

    public function test_peer_review_page_visible_only_when_eligible(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture();
        $this->openPeerEval($assessment);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.peer-review', $teamB))
            ->assertOk()
            ->assertSee($teamB->title);

        // Own team is not peer-reviewable for cross-team eval.
        $this->actingAs($students[0])
            ->get(route('projects.peer-review', $teamA))
            ->assertForbidden();

        // Team without submission: rebuild B without submission via a third empty team.
        [$course2, $admin2, $students2, $assessment2, $own, $empty] = $this->fixture(withTeamBSubmission: false);
        $this->openPeerEval($assessment2);
        app(CourseContextService::class)->setCurrentCourse($students2[0], $course2->course_id);
        $this->actingAs($students2[0])
            ->get(route('projects.peer-review', $empty))
            ->assertForbidden();
    }

    public function test_open_now_publishes_peer_eval_window(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment] = array_slice($this->fixture(), 0, 4);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.peer-eval.open', $assessment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $assessment->refresh();
        $this->assertTrue((bool) $assessment->peer_eval_enabled);
        $this->assertNotNull($assessment->peer_eval_opens_at);
        $this->assertTrue(app(ProjectPeerEvaluationService::class)->isOpen($assessment));
        $this->assertSame('open', app(ProjectPeerEvaluationService::class)->status($assessment));
    }

    public function test_api_exposes_eligible_teams_not_teammates(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture();
        $this->openPeerEval($assessment);

        Sanctum::actingAs($students[0]);
        $this->getJson('/api/v1/projects/'.$teamA->project_id.'/peer-ratings/pending')
            ->assertOk()
            ->assertJsonPath('data.open', true)
            ->assertJsonCount(1, 'data.eligible')
            ->assertJsonPath('data.eligible.0.project_id', (int) $teamB->project_id)
            ->assertJsonMissing(['user_id' => (int) $students[1]->user_id])
            ->assertJsonMissing(['rater_user_id']);
    }

    public function test_peer_ratings_are_tenant_scoped(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture();
        $this->openPeerEval($assessment);

        app(ProjectPeerEvaluationService::class)->submitTeamRatings($assessment, $students[0], [
            ['ratee_project_id' => $teamB->project_id, 'score' => 5],
        ]);

        $rating = ProjectPeerRating::query()->firstOrFail();
        $this->assertNotNull($rating->church_id);

        $otherChurch = $this->createChurch(['slug' => 'peer-other-church']);
        app(TenantContext::class)->set($otherChurch);

        $this->assertSame(
            0,
            ProjectPeerRating::query()->whereKey($rating->project_peer_rating_id)->count()
        );
    }

    private function openPeerEval($assessment): void
    {
        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
            'peer_eval_scale_max' => 5,
            'peer_eval_min_picks' => 1,
            'peer_eval_max_picks' => 3,
        ]);
    }

    /**
     * Two teams: student0 on A, student1 on B. B has a submission by default.
     *
     * @return array{0: Course, 1: User, 2: list<User>, 3: \App\Models\ProjectAssessment, 4: Project, 5: Project}
     */
    private function fixture(bool $withTeamBSubmission = true): array
    {
        $course = $this->createCourse(['title' => 'Peer Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Peer Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'peer-admin-'.uniqid().'@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => 'peer-student'.uniqid()."{$i}@example.com",
                'first_name' => 'Peer',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Peer Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);

        $projects = $assessment->projects()->orderBy('sort_order')->get();
        $teamA = $projects[0];
        $teamB = $projects[1];

        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $teamA->project_id,
            'user_id' => $students[0]->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $teamB->project_id,
            'user_id' => $students[1]->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        if ($withTeamBSubmission) {
            $this->addSubmission($assessment, $teamB, $students[1]);
        }

        return [$course, $admin, $students, $assessment->fresh(), $teamA->fresh(), $teamB->fresh()];
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: \App\Models\ProjectAssessment, 4: Project, 5: Project, 6: Project}
     */
    private function fixtureThreeTeams(): array
    {
        [$course, $admin, $students, $assessment, $teamA, $teamB] = $this->fixture();

        $teamC = Project::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'title' => 'Team C '.uniqid(),
            'status' => Project::STATUS_OPEN,
            'sort_order' => 2,
        ]);
        $studentC = $this->createUser([
            'email' => 'peer-student-c-'.uniqid().'@example.com',
            'first_name' => 'Peer',
            'second_name' => 'C',
        ]);
        $studentRole = $course->roles()->where('name', 'student')->first()
            ?? $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $this->assignCourseRole($studentC, $course, $studentRole);
        $students[] = $studentC;

        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $teamC->project_id,
            'user_id' => $studentC->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        $this->addSubmission($assessment, $teamC, $studentC);

        return [$course, $admin, $students, $assessment->fresh(), $teamA->fresh(), $teamB->fresh(), $teamC->fresh()];
    }

    private function addSubmission($assessment, Project $project, User $submitter): void
    {
        $deliverable = ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'Draft',
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'is_required' => true,
            'allow_late' => true,
        ]);

        ProjectDeliverableSubmission::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'project_deliverable_id' => $deliverable->project_deliverable_id,
            'submitted_by_user_id' => $submitter->user_id,
            'body' => 'Peer review draft',
            'submitted_at' => now(),
            'is_late' => false,
        ]);
    }
}
