<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectSubmissionService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

/**
 * Mobile API guard rails for team projects: permission keys, ownership of the
 * team and the deliverable, and the same 404/422 shapes the web surface uses.
 */
class ProjectApiGuardsTest extends EventModuleTestCase
{
    public function test_joining_twice_is_a_validation_error(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        Sanctum::actingAs($students[0]);

        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertCreated();

        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertStatus(422)
            ->assertJsonValidationErrors('project');
    }

    public function test_leaving_without_a_seat_is_a_validation_error(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        Sanctum::actingAs($students[0]);

        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave")
            ->assertStatus(422)
            ->assertJsonValidationErrors('project');
    }

    public function test_an_unpublished_assessment_is_not_found_for_join_or_leave(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $assessment->update(['is_published' => false]);
        Sanctum::actingAs($students[0]);

        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertNotFound();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave")
            ->assertNotFound();
    }

    public function test_the_join_permission_is_required_to_take_a_seat(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();

        $readOnlyRole = $this->courseRoleWithPermissions($course, 'observer', ['project.view']);
        $observer = $this->createUser(['email' => 'api-project-observer@example.com']);
        $this->assignCourseRole($observer, $course, $readOnlyRole);
        Sanctum::actingAs($observer);

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")->assertOk();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertForbidden();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave")
            ->assertForbidden();

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $observer->user_id));
    }

    public function test_submitting_a_deliverable_that_belongs_to_another_team_is_not_found(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $mine = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $other = $assessment->projects()->where('project_id', '!=', $mine->project_id)->firstOrFail();
        $foreignDeliverable = $this->deliverableFor($other, ProjectDeliverable::TYPE_TEXT);

        Sanctum::actingAs($students[0]);
        $this->postJson(
            "/api/v1/projects/{$mine->project_id}/deliverables/{$foreignDeliverable->project_deliverable_id}/submit",
            ['body' => 'Wrong team deliverable.']
        )->assertNotFound();

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    public function test_deleting_a_file_that_belongs_to_another_team_is_not_found(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $assign = app(ProjectAssignmentService::class);
        $mine = $assign->assignStudent($assessment, $students[0], notify: false);
        $theirs = $assign->assignStudent($assessment, $students[1], notify: false);

        $theirDeliverable = $this->deliverableFor($theirs, ProjectDeliverable::TYPE_LINK);
        $theirSubmission = app(ProjectSubmissionService::class)->submit(
            $theirs,
            $theirDeliverable,
            $students[1],
            ['link_url' => 'https://drive.example.com/theirs'],
        );
        $file = ProjectSubmissionFile::create([
            'project_deliverable_submission_id' => $theirSubmission->project_deliverable_submission_id,
            'uploaded_by_user_id' => $students[1]->user_id,
            'file_path' => 'project-submissions/theirs.pdf',
            'original_name' => 'theirs.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        Sanctum::actingAs($students[0]);
        $this->deleteJson(
            "/api/v1/projects/{$mine->project_id}/submission-files/{$file->project_submission_file_id}"
        )->assertNotFound();

        $this->assertSame(1, ProjectSubmissionFile::query()->count());
    }

    public function test_deleting_a_file_after_the_deliverable_closed_is_rejected(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_LINK);

        $submission = app(ProjectSubmissionService::class)->submit(
            $project,
            $deliverable,
            $students[0],
            ['link_url' => 'https://drive.example.com/mine'],
        );
        $file = ProjectSubmissionFile::create([
            'project_deliverable_submission_id' => $submission->project_deliverable_submission_id,
            'uploaded_by_user_id' => $students[0]->user_id,
            'file_path' => 'project-submissions/mine.pdf',
            'original_name' => 'mine.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        $deliverable->forceFill(['due_at' => now()->subDay(), 'allow_late' => false])->save();

        Sanctum::actingAs($students[0]);
        $this->deleteJson(
            "/api/v1/projects/{$project->project_id}/submission-files/{$file->project_submission_file_id}"
        )->assertStatus(422);

        $this->assertSame(1, ProjectSubmissionFile::query()->count());
    }

    public function test_submitting_after_the_deadline_is_rejected_when_late_work_is_off(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_TEXT, [
            'due_at' => now()->subDay(),
            'allow_late' => false,
        ]);

        Sanctum::actingAs($students[0]);
        $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            ['body' => 'Too late.']
        )->assertStatus(422)->assertJsonValidationErrors('deliverable');

        $this->getJson("/api/v1/projects/{$project->project_id}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.overdue', true)
            ->assertJsonPath('data.deliverables.0.open', false)
            ->assertJsonPath('data.deliverables.0.submitted', false);
    }

    public function test_a_late_but_accepted_submission_is_flagged_in_the_payload(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_TEXT, [
            'due_at' => now()->subDay(),
            'allow_late' => true,
        ]);

        Sanctum::actingAs($students[0]);
        $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            ['body' => 'Late but accepted.']
        )->assertCreated()->assertJsonPath('data.is_late', true);

        $this->getJson("/api/v1/projects/{$project->project_id}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.late', true)
            ->assertJsonPath('data.progress.missing', 0);
    }

    public function test_every_project_endpoint_requires_authentication(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_TEXT);

        $this->getJson("/api/v1/projects/{$project->project_id}")->assertUnauthorized();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave")
            ->assertUnauthorized();
        $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            ['body' => 'anonymous']
        )->assertUnauthorized();
        $this->deleteJson("/api/v1/projects/{$project->project_id}/submission-files/1")
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverableFor(Project $project, string $type, array $attributes = []): ProjectDeliverable
    {
        return ProjectDeliverable::create(array_merge([
            'project_id' => $project->project_id,
            'title' => 'API guard deliverable',
            'sort_order' => 0,
            'submission_type' => $type,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
            'is_required' => true,
            'allow_late' => true,
        ], $attributes));
    }

    /**
     * Published assessment with two single-seat teams and two unseated students.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<User>}
     */
    private function fixture(): array
    {
        $course = $this->createCourse(['title' => 'API Guard Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'API Guard Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'api-guard-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "api-guard-s{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'API Guard Assessment',
            'min_team_size' => 1,
            'max_team_size' => 1,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 2,
        ], $admin);
        $assessment->update(['is_published' => true]);

        return [$course, $assessment->fresh(), $admin, $students];
    }
}
