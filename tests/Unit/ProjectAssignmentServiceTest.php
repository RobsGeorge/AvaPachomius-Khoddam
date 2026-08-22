<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectAssignmentServiceTest extends EventModuleTestCase
{
    public function test_pack_fill_finishes_started_team_before_opening_empty(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 3, min: 2, max: 2);

        $service = app(ProjectAssignmentService::class);
        $first = $service->assignStudent($assessment, $students[0]);
        $second = $service->assignStudent($assessment, $students[1]);

        $this->assertSame($first->project_id, $second->project_id);
        $this->assertTrue($second->fresh()->isClosed());

        $third = $service->assignStudent($assessment, $students[2]);
        $this->assertNotSame($first->project_id, $third->project_id);
        $this->assertSame(1, $third->activeMemberCount());
    }

    public function test_never_opens_second_empty_while_a_started_team_has_seats(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 3, min: 2, max: 3);

        $service = app(ProjectAssignmentService::class);
        $first = $service->assignStudent($assessment, $students[0]);

        $ids = [];
        foreach (array_slice($students, 1, 2) as $student) {
            $ids[] = $service->assignStudent($assessment, $student)->project_id;
        }

        $this->assertSame([$first->project_id, $first->project_id], $ids);
        $this->assertSame(3, $first->fresh()->activeMemberCount());
    }

    public function test_join_fails_when_every_team_is_full(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 1, min: 1, max: 1);

        $service = app(ProjectAssignmentService::class);
        $service->assignStudent($assessment, $students[0]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assignStudent($assessment, $students[1]);
    }

    public function test_leaving_a_full_team_reopens_it(): void
    {
        Mail::fake();
        [$assessment, $students] = $this->assessmentWithStudents(projectCount: 1, min: 1, max: 1);
        $service = app(ProjectAssignmentService::class);
        $project = $service->assignStudent($assessment, $students[0]);
        $this->assertTrue($project->fresh()->isClosed());

        $membership = ProjectMembership::query()
            ->where('project_id', $project->project_id)
            ->where('user_id', $students[0]->user_id)
            ->first();
        $service->leaveTeam($membership, $assessment);

        $this->assertTrue($project->fresh()->isOpen());
        $this->assertSame(0, $project->fresh()->activeMemberCount());
    }

    /**
     * @return array{0: ProjectAssessment, 1: list<User>}
     */
    private function assessmentWithStudents(int $projectCount, int $min, int $max): array
    {
        $course = $this->createCourse(['title' => 'Pack Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Pack Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $creator = $this->createUser(['email' => 'pack-admin@example.com']);
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Pack Assessment',
            'min_team_size' => $min,
            'max_team_size' => $max,
            'project_count' => $projectCount,
        ], $creator);

        $students = [];
        for ($i = 0; $i < 6; $i++) {
            $students[] = $this->createUser(['email' => "pack-s{$i}@example.com"]);
        }

        return [$assessment, $students];
    }
}
