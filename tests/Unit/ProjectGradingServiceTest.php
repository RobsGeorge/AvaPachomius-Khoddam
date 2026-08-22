<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectMemberGrade;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectGradingServiceTest extends EventModuleTestCase
{
    public function test_criteria_sum_becomes_max_points_and_team_grade_propagates(): void
    {
        Mail::fake();
        [$assessment, $students, $admin] = $this->gradedReadyAssessment();
        $grading = app(ProjectGradingService::class);

        $assessment = $grading->syncCriteria($assessment, [
            ['title' => 'Research', 'max_points' => 40],
            ['title' => 'Delivery', 'max_points' => 60],
        ]);

        $this->assertSame(100.0, $grading->maxPoints($assessment));
        $this->assertSame(100.0, (float) $assessment->max_points);

        $project = $assessment->projects()->first();
        $criteria = $assessment->criteria;
        $grade = $grading->gradeTeam(
            $assessment,
            $project,
            [
                $criteria[0]->project_grade_criterion_id => 30,
                $criteria[1]->project_grade_criterion_id => 45,
            ],
            $admin
        );

        $this->assertSame(75.0, (float) $grade->points);
        $this->assertSame(75.0, (float) $grade->percent);
        $this->assertTrue($grading->passed((float) $grade->percent, $assessment));

        foreach ($students as $student) {
            $member = ProjectMemberGrade::query()
                ->where('project_assessment_id', $assessment->project_assessment_id)
                ->where('user_id', $student->user_id)
                ->first();
            $this->assertNotNull($member);
            $this->assertSame(75.0, (float) $member->points);
            $this->assertSame(ProjectMemberGrade::SOURCE_TEAM, $member->source);
        }
    }

    public function test_student_override_survives_team_regrade_and_can_revert(): void
    {
        Mail::fake();
        [$assessment, $students, $admin] = $this->gradedReadyAssessment();
        $grading = app(ProjectGradingService::class);
        $project = $assessment->projects()->first();

        $grading->gradeTeam($assessment, $project, [], $admin, null, 80);
        $grading->gradeStudent($assessment, $students[0], 50, $admin);
        $grading->gradeTeam($assessment, $project, [], $admin, null, 90);

        $overridden = ProjectMemberGrade::query()
            ->where('user_id', $students[0]->user_id)
            ->first();
        $teammate = ProjectMemberGrade::query()
            ->where('user_id', $students[1]->user_id)
            ->first();

        $this->assertSame(50.0, (float) $overridden->points);
        $this->assertSame(ProjectMemberGrade::SOURCE_OVERRIDE, $overridden->source);
        $this->assertSame(90.0, (float) $teammate->points);
        $this->assertSame(ProjectMemberGrade::SOURCE_TEAM, $teammate->source);

        $reverted = $grading->clearStudentOverride($assessment, $students[0], $admin);
        $this->assertSame(90.0, (float) $reverted->points);
        $this->assertSame(ProjectMemberGrade::SOURCE_TEAM, $reverted->source);
    }

    /**
     * @return array{0: \App\Models\ProjectAssessment, 1: list<\App\Models\User>, 2: \App\Models\User}
     */
    private function gradedReadyAssessment(): array
    {
        $course = $this->createCourse(['title' => 'Grade Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Grade Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage', 'project.grade']);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $admin = $this->createUser(['email' => 'prj-grade-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "prj-grade-s{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Graded Project',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        $assign = app(ProjectAssignmentService::class);
        foreach ($students as $student) {
            $assign->assignStudent($assessment, $student, notify: false);
        }

        return [$assessment->fresh('projects'), $students, $admin];
    }
}
