<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableGrade;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectTeamGrade;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ProjectDeliverableGradingTest extends EventModuleTestCase
{
    public function test_deliverable_mode_requires_points_to_sum_to_max(): void
    {
        Mail::fake();
        [$course, $admin, $assessment, $project, $d1, $d2] = $this->fixture();

        $d1->update(['max_points' => 40]);
        $d2->update(['max_points' => 40]);

        $this->expectException(ValidationException::class);
        app(ProjectGradingService::class)->setGradingMode($assessment, 'deliverables', $admin);
    }

    public function test_grader_scores_deliverables_and_rolls_up_to_team_and_members(): void
    {
        Mail::fake();
        [$course, $admin, $assessment, $project, $d1, $d2, $students] = $this->fixture(withMembers: true);

        $d1->update(['max_points' => 60]);
        $d2->update(['max_points' => 40]);
        app(ProjectGradingService::class)->setGradingMode($assessment->fresh(), 'deliverables', $admin);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.team', $project), [
                'scores' => [
                    $d1->project_deliverable_id => 54,
                    $d2->project_deliverable_id => 30,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $teamGrade = ProjectTeamGrade::query()->where('project_id', $project->project_id)->firstOrFail();
        $this->assertEqualsWithDelta(84.0, (float) $teamGrade->points, 0.01);
        $this->assertEqualsWithDelta(84.0, (float) $teamGrade->percent, 0.01);

        $this->assertSame(2, ProjectDeliverableGrade::query()->where('project_id', $project->project_id)->count());

        $memberGrade = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->where('user_id', $students[0]->user_id)
            ->firstOrFail();
        $this->assertEqualsWithDelta(84.0, (float) $memberGrade->points, 0.01);
        $this->assertSame(ProjectMemberGrade::SOURCE_TEAM, $memberGrade->source);
    }

    /**
     * @return array{0: Course, 1: User, 2: \App\Models\ProjectAssessment, 3: \App\Models\Project, 4: ProjectDeliverable, 5: ProjectDeliverable, 6?: list<User>}
     */
    private function fixture(bool $withMembers = false): array
    {
        $course = $this->createCourse(['title' => 'Deliv Grade Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Deliv Grade Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'deliv-grade-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Deliv Grade Assessment',
            'min_team_size' => 1,
            'max_team_size' => 3,
            'max_points' => 100,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);

        $project = $assessment->projects()->firstOrFail();
        $d1 = ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'Part A',
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'max_points' => 50,
        ]);
        $d2 = ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'Part B',
            'sort_order' => 1,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'max_points' => 50,
        ]);

        $students = [];
        if ($withMembers) {
            $studentRole = $this->courseRoleWithPermissions($course, 'student', [
                'project.view', 'project.join',
            ]);
            $student = $this->createUser(['email' => 'deliv-grade-student@example.com']);
            $this->assignCourseRole($student, $course, $studentRole);
            ProjectMembership::create([
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'user_id' => $student->user_id,
                'status' => ProjectMembership::STATUS_ACTIVE,
                'assigned_at' => now(),
            ]);
            $students[] = $student;
        }

        return [$course, $admin, $assessment->fresh(), $project->fresh(), $d1, $d2, $students];
    }
}
