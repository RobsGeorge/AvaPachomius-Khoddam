<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectTeamGradeCriterion;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectTeamRubricFlowTest extends EventModuleTestCase
{
    public function test_grades_screen_shows_the_shared_rubric_editor_per_team(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->get(route('projects.grades', $assessment))
            ->assertOk()
            ->assertSee(__('projects.team_rubric'))
            ->assertSee(__('projects.team_rubric_add_criterion'))
            ->assertSee('Research')
            ->assertSee('Delivery');
    }

    public function test_admin_saves_a_per_team_rubric_and_grades_against_it(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $criteria = $assessment->criteria;
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->put(route('projects.grades.team-criteria', $projects[0]), [
                'team_criteria' => [
                    ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => '40'],
                    ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => '40'],
                    ['title' => 'Field visit', 'max_points' => '20'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, ProjectTeamGradeCriterion::query()->where('project_id', $projects[0]->project_id)->count());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.team_criteria_synced')->exists());

        $effective = app(ProjectGradingService::class)->effectiveCriteria($assessment, $projects[0]);
        $this->actingAs($admin)
            ->post(route('projects.grades.team', $projects[0]), [
                'scores' => [
                    $effective[0]['key'] => '35',
                    $effective[1]['key'] => '35',
                    $effective[2]['key'] => '15',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $grade = $projects[0]->fresh()->teamGrade;
        $this->assertSame(85.0, (float) $grade->points);
        $this->assertSame(85.0, (float) $grade->percent);
    }

    public function test_mismatched_rubric_total_is_rejected_with_a_field_error(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $criteria = $assessment->criteria;
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->put(route('projects.grades.team-criteria', $projects[0]), [
                'team_criteria' => [
                    ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => '40'],
                    ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => '60'],
                    ['title' => 'Bonus', 'max_points' => '15'],
                ],
            ])
            ->assertSessionHasErrors('team_criteria');

        $this->assertSame(0, ProjectTeamGradeCriterion::query()->count());
    }

    public function test_admin_resets_a_team_back_to_the_shared_rubric(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $criteria = $assessment->criteria;
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        app(ProjectGradingService::class)->syncTeamCriteria($assessment, $projects[0], [
            ['project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id, 'max_points' => 70],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 30],
        ], $admin);

        $this->actingAs($admin)
            ->delete(route('projects.grades.team-criteria.reset', $projects[0]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ProjectTeamGradeCriterion::query()->count());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.team_criteria_reset')->exists());
    }

    public function test_a_criterion_from_another_assessment_is_rejected(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $other = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $assessment->module_id,
            'title' => 'Other Rubric Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $other = app(ProjectGradingService::class)->syncCriteria($other, [
            ['title' => 'Foreign', 'max_points' => 100],
        ]);

        $this->actingAs($admin)
            ->put(route('projects.grades.team-criteria', $projects[0]), [
                'team_criteria' => [
                    ['project_grade_criterion_id' => $other->criteria->first()->project_grade_criterion_id, 'max_points' => '100'],
                ],
            ])
            ->assertNotFound();
    }

    public function test_student_sees_the_rubric_breakdown_only_after_announcement(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $criteria = $assessment->criteria;
        $grading = app(ProjectGradingService::class);

        $grading->gradeTeam($assessment, $projects[0], [
            $criteria[0]->project_grade_criterion_id => 20,
            $criteria[1]->project_grade_criterion_id => 30,
        ], $admin);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.show', $projects[0]))
            ->assertOk()
            ->assertDontSee(__('projects.rubric_breakdown'))
            ->assertSee(__('projects.score_pending_announcement'));

        $grading->announce($assessment, $admin);

        $this->actingAs($students[0])
            ->get(route('projects.show', $projects[0]))
            ->assertOk()
            ->assertSee(__('projects.rubric_breakdown'))
            ->assertSee('Research')
            ->assertSee('50%');
    }

    public function test_student_sees_their_team_specific_criterion_titles(): void
    {
        [$course, $assessment, $admin, $projects, $students] = $this->fixture();
        $criteria = $assessment->criteria;
        $grading = app(ProjectGradingService::class);

        $grading->syncTeamCriteria($assessment, $projects[0], [
            [
                'project_grade_criterion_id' => $criteria[0]->project_grade_criterion_id,
                'title' => 'Home visit research',
                'max_points' => 40,
            ],
            ['project_grade_criterion_id' => $criteria[1]->project_grade_criterion_id, 'max_points' => 60],
        ], $admin);

        $effective = $grading->effectiveCriteria($assessment, $projects[0]);
        $grading->gradeTeam($assessment, $projects[0], [
            $effective[0]['key'] => 40,
            $effective[1]['key'] => 60,
        ], $admin);
        $grading->announce($assessment, $admin);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.show', $projects[0]))
            ->assertOk()
            ->assertSee('Home visit research');
    }

    /**
     * Published assessment, shared rubric (40 + 60), two teams, one seated student.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<Project>, 4: list<User>}
     */
    private function fixture(): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Rubric Flow Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Rubric Flow Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'rubric-flow-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "rubric-flow-s{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Rubric Flow Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $assessment = app(ProjectGradingService::class)->syncCriteria($assessment, [
            ['title' => 'Research', 'max_points' => 40],
            ['title' => 'Delivery', 'max_points' => 60],
        ]);

        $assign = app(ProjectAssignmentService::class);
        $assign->assignStudent($assessment, $students[0], notify: false);

        $projects = $assessment->projects()->orderBy('sort_order')->get()->all();

        return [$course, $assessment->fresh('criteria'), $admin, $projects, $students];
    }
}
