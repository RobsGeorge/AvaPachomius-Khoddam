<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectTeamCriterionScore;
use App\Models\ProjectTeamGradeCriterion;
use App\Models\User;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ProjectTeamRubricTest extends EventModuleTestCase
{
    public function test_a_team_without_overrides_uses_the_shared_rubric(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);

        $this->assertCount(2, $effective);
        $this->assertSame(['Research', 'Delivery'], array_column($effective, 'title'));
        $this->assertSame([40.0, 60.0], array_column($effective, 'max_points'));
        $this->assertFalse($grading->teamHasCustomRubric($projects[0]));
        $this->assertSame(100.0, $grading->effectiveMaxPoints($assessment, $projects[0]));
    }

    public function test_a_team_can_reweight_shared_criteria(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 70],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 30],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $this->assertSame([70.0, 30.0], array_column($effective, 'max_points'));
        $this->assertTrue($grading->teamHasCustomRubric($projects[0]));

        // The other team is untouched.
        $this->assertSame(
            [40.0, 60.0],
            array_column($grading->effectiveCriteria($assessment, $projects[1]), 'max_points')
        );
    }

    public function test_a_team_can_rename_a_shared_criterion(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            [
                'project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id,
                'title' => 'Field research',
                'max_points' => 40,
            ],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 60],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $this->assertSame('Field research', $effective[0]['title']);
        $this->assertSame('Delivery', $effective[1]['title']);
    }

    public function test_a_team_can_add_its_own_criterion_when_the_total_still_matches(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 40],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 40],
            ['title' => 'Field visit', 'max_points' => 20],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $this->assertCount(3, $effective);
        $this->assertSame('Field visit', $effective[2]['title']);
        $this->assertSame('team', $effective[2]['kind']);
        $this->assertSame(100.0, $grading->effectiveMaxPoints($assessment, $projects[0]));
    }

    public function test_a_team_can_drop_a_shared_criterion(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'is_excluded' => true],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 100],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $this->assertCount(1, $effective);
        $this->assertSame('Delivery', $effective[0]['title']);
        $this->assertSame(100.0, $effective[0]['max_points']);
    }

    public function test_rubric_is_rejected_when_the_total_does_not_match_the_assessment_maximum(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        try {
            $grading->syncTeamCriteria($assessment, $projects[0], [
                ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 40],
                ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 60],
                ['title' => 'Bonus', 'max_points' => 10],
            ], $admin);
            $this->fail('Expected the sum validation to reject the rubric.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('team_criteria', $e->errors());
        }

        // Nothing was persisted.
        $this->assertSame(0, ProjectTeamGradeCriterion::query()->count());
        $this->assertSame(
            [40.0, 60.0],
            array_column($grading->effectiveCriteria($assessment, $projects[0]), 'max_points')
        );
    }

    public function test_rubric_is_rejected_when_a_shared_criterion_is_adjusted_twice(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture();
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $this->expectException(ValidationException::class);
        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 50],
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 50],
        ], $admin);
    }

    public function test_rubric_customisation_requires_shared_criteria(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(withCriteria: false);

        $this->expectException(ValidationException::class);
        app(ProjectGradingService::class)->syncTeamCriteria($assessment, $projects[0], [
            ['title' => 'Only criterion', 'max_points' => 100],
        ], $admin);
    }

    public function test_team_is_graded_against_its_own_rubric(): void
    {
        [$assessment, $projects, $admin, $students] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 40],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 40],
            ['title' => 'Field visit', 'max_points' => 20],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $grade = $grading->gradeTeam($assessment, $projects[0], [
            $effective[0]['key'] => 30,
            $effective[1]['key'] => 30,
            $effective[2]['key'] => 20,
        ], $admin);

        $this->assertSame(80.0, (float) $grade->points);
        $this->assertSame(80.0, (float) $grade->percent);
        $this->assertSame(1, ProjectTeamCriterionScore::query()->count());

        $member = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $students[0]->user_id)
            ->firstOrFail();
        $this->assertSame(80.0, (float) $member->points);
    }

    public function test_score_above_the_team_criterion_maximum_is_rejected(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 20],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 80],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);

        $this->expectException(ValidationException::class);
        $grading->gradeTeam($assessment, $projects[0], [
            $effective[0]['key'] => 35,
            $effective[1]['key'] => 40,
        ], $admin);
    }

    public function test_dropped_criterion_no_longer_needs_a_score(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'is_excluded' => true],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 100],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $grade = $grading->gradeTeam($assessment, $projects[0], [
            $effective[0]['key'] => 90,
        ], $admin);

        $this->assertSame(90.0, (float) $grade->points);
        $this->assertSame(90.0, (float) $grade->percent);
    }

    public function test_resetting_the_rubric_regrades_against_the_shared_criteria(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 40],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 40],
            ['title' => 'Field visit', 'max_points' => 20],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $grading->gradeTeam($assessment, $projects[0], [
            $effective[0]['key'] => 40,
            $effective[1]['key'] => 40,
            $effective[2]['key'] => 20,
        ], $admin);

        $grading->resetTeamCriteria($assessment, $projects[0], $admin);

        $this->assertFalse($grading->teamHasCustomRubric($projects[0]));
        $this->assertSame(0, ProjectTeamCriterionScore::query()->count());

        // The team-only 20 points are gone; the two shared scores remain (40 + 40),
        // and Delivery is capped back to its shared maximum of 60.
        $grade = $projects[0]->fresh()->teamGrade;
        $this->assertSame(80.0, (float) $grade->points);
    }

    public function test_editing_the_shared_rubric_clears_team_overrides(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $grading->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 70],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 30],
        ], $admin);
        $this->assertTrue($grading->teamHasCustomRubric($projects[0]));

        $grading->syncCriteria($assessment, [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'title' => 'Research', 'max_points' => 50],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'title' => 'Delivery', 'max_points' => 50],
        ]);

        $this->assertFalse($grading->teamHasCustomRubric($projects[0]));
        $this->assertSame(
            [50.0, 50.0],
            array_column($grading->effectiveCriteria($assessment->fresh('criteria'), $projects[0]), 'max_points')
        );
    }

    public function test_breakdown_reports_points_and_percent_per_criterion(): void
    {
        [$assessment, $projects, $admin] = $this->rubricFixture(seat: true);
        $grading = app(ProjectGradingService::class);
        $criteria = $assessment->criteria;

        $breakdown = $grading->criterionBreakdown($assessment, $projects[0]);
        $this->assertNull($breakdown[0]['points']);
        $this->assertNull($breakdown[0]['percent']);

        $grading->gradeTeam($assessment, $projects[0], [
            $criteria[0]->project_grade_criterion_id => 20,
            $criteria[1]->project_grade_criterion_id => 30,
        ], $admin);

        $breakdown = $grading->criterionBreakdown($assessment, $projects[0]);
        $this->assertSame(20.0, $breakdown[0]['points']);
        $this->assertSame(50.0, $breakdown[0]['percent']);
        $this->assertSame(30.0, $breakdown[1]['points']);
        $this->assertSame(50.0, $breakdown[1]['percent']);
    }

    /**
     * @return array{0: ProjectAssessment, 1: list<Project>, 2: User, 3: list<User>}
     */
    private function rubricFixture(bool $withCriteria = true, bool $seat = false): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Rubric Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Rubric Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $admin = $this->createUser(['email' => 'rubric-admin@example.com']);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Rubric Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $grading = app(ProjectGradingService::class);
        if ($withCriteria) {
            $assessment = $grading->syncCriteria($assessment, [
                ['title' => 'Research', 'max_points' => 40],
                ['title' => 'Delivery', 'max_points' => 60],
            ]);
        }

        $students = [];
        if ($seat) {
            $assign = app(ProjectAssignmentService::class);
            for ($i = 0; $i < 2; $i++) {
                $student = $this->createUser(['email' => "rubric-s{$i}@example.com"]);
                $assign->assignStudent($assessment, $student, notify: false);
                $students[] = $student;
            }
        }

        $projects = $assessment->projects()->orderBy('sort_order')->get()->all();

        return [$assessment->fresh('criteria'), $projects, $admin, $students];
    }
}
