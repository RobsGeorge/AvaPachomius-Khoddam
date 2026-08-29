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
use App\Services\ProjectGradingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class ProjectApiTest extends EventModuleTestCase
{
    public function test_assessment_list_reports_the_join_window_and_my_team(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        Sanctum::actingAs($students[0]);

        $response = $this->getJson("/api/v1/courses/{$course->course_id}/projects");

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'API Assessment')
            ->assertJsonPath('data.0.join_window_open', true)
            ->assertJsonPath('data.0.can_join', true)
            ->assertJsonPath('data.0.can_leave', false)
            ->assertJsonPath('data.0.my_project_id', null)
            ->assertJsonPath('data.0.max_points', 100);
    }

    public function test_unpublished_assessments_are_hidden(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $assessment->update(['is_published' => false]);
        Sanctum::actingAs($students[0]);

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_student_without_the_view_permission_is_forbidden(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $outsiderRole = $this->courseRoleWithPermissions($course, 'guest', []);
        $outsider = $this->createUser(['email' => 'api-project-guest@example.com']);
        $this->assignCourseRole($outsider, $course, $outsiderRole);
        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")->assertForbidden();
    }

    public function test_join_seats_the_student_and_leave_reassigns_once(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        Sanctum::actingAs($students[0]);

        $joined = $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join");
        $joined->assertCreated();
        $firstTeam = (int) $joined->json('data.project_id');

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")
            ->assertJsonPath('data.0.can_join', false)
            ->assertJsonPath('data.0.can_leave', true)
            ->assertJsonPath('data.0.my_project_id', $firstTeam);

        $left = $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave");
        $left->assertOk();
        $this->assertNotSame($firstTeam, (int) $left->json('data.project_id'));

        // The single change chance is spent.
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/leave")
            ->assertStatus(422);
    }

    public function test_join_is_closed_after_the_join_window(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $assessment->update(['join_closes_at' => now()->subDay()]);
        Sanctum::actingAs($students[0]);

        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertStatus(422);

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")
            ->assertJsonPath('data.0.join_window_open', false)
            ->assertJsonPath('data.0.can_join', false);
    }

    public function test_team_detail_returns_roster_deliverables_and_hidden_grade(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_LINK);

        Sanctum::actingAs($students[0]);
        $response = $this->getJson("/api/v1/projects/{$project->project_id}");

        $response->assertOk()
            ->assertJsonPath('data.project_id', (int) $project->project_id)
            ->assertJsonPath('data.members.0.user_id', (int) $students[0]->user_id)
            ->assertJsonPath('data.members.0.is_self', true)
            ->assertJsonPath('data.deliverables.0.project_deliverable_id', (int) $deliverable->project_deliverable_id)
            ->assertJsonPath('data.deliverables.0.submission_type', ProjectDeliverable::TYPE_LINK)
            ->assertJsonPath('data.deliverables.0.submitted', false)
            ->assertJsonPath('data.progress.required', 1)
            ->assertJsonPath('data.progress.missing', 1)
            ->assertJsonPath('data.grade.can_view', false)
            ->assertJsonPath('data.grade.reason', 'pending_announcement')
            ->assertJsonPath('data.rubric', []);
    }

    public function test_a_student_cannot_read_another_team(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $mine = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $other = $assessment->projects()
            ->where('project_id', '!=', $mine->project_id)
            ->firstOrFail();

        Sanctum::actingAs($students[0]);
        $this->getJson("/api/v1/projects/{$other->project_id}")->assertForbidden();
    }

    public function test_announced_grade_and_rubric_percentages_are_returned(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);

        $grading = app(ProjectGradingService::class);
        $assessment = $grading->syncCriteria($assessment, [
            ['title' => 'Research', 'max_points' => 40],
            ['title' => 'Presentation', 'max_points' => 60],
        ]);
        $criteria = $assessment->criteria;
        $grading->gradeTeam($assessment, $project, [
            $criteria[0]->project_grade_criterion_id => 20,
            $criteria[1]->project_grade_criterion_id => 60,
        ], $admin);
        $grading->announce($assessment, $admin);

        Sanctum::actingAs($students[0]);
        $this->getJson("/api/v1/projects/{$project->project_id}")
            ->assertOk()
            ->assertJsonPath('data.grade.can_view', true)
            ->assertJsonPath('data.grade.points', 80)
            ->assertJsonPath('data.grade.percent', 80)
            ->assertJsonPath('data.grade.passed', true)
            ->assertJsonPath('data.rubric.0.title', 'Research')
            ->assertJsonPath('data.rubric.0.percent', 50)
            ->assertJsonPath('data.rubric.1.percent', 100);
    }

    public function test_file_deliverable_submit_and_file_delete(): void
    {
        Storage::fake('public');
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_PDF, [
            'file_mode' => ProjectDeliverable::FILE_MODE_MULTI,
        ]);

        Sanctum::actingAs($students[0]);
        $submitted = $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            [
                'body' => 'Draft attached.',
                'files' => [UploadedFile::fake()->create('report.pdf', 32, 'application/pdf')],
            ]
        );

        $submitted->assertCreated()
            ->assertJsonPath('data.body', 'Draft attached.')
            ->assertJsonPath('data.is_late', false)
            ->assertJsonPath('data.files.0.name', 'report.pdf');

        $file = ProjectSubmissionFile::query()->firstOrFail();
        Storage::disk('public')->assertExists($file->file_path);

        $this->getJson("/api/v1/projects/{$project->project_id}")
            ->assertJsonPath('data.deliverables.0.submitted', true)
            ->assertJsonPath('data.progress.missing', 0);

        $this->deleteJson(
            "/api/v1/projects/{$project->project_id}/submission-files/{$file->project_submission_file_id}"
        )->assertOk();

        Storage::disk('public')->assertMissing($file->file_path);
        $this->assertSame(0, ProjectSubmissionFile::query()->count());
    }

    public function test_link_deliverable_requires_a_valid_url(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_LINK);

        Sanctum::actingAs($students[0]);
        $url = "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit";

        $this->postJson($url, ['link_url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('link_url');

        $this->postJson($url, ['link_url' => 'https://drive.example.com/team'])
            ->assertCreated()
            ->assertJsonPath('data.link_url', 'https://drive.example.com/team');

        $this->assertSame(1, ProjectDeliverableSubmission::query()->count());
    }

    public function test_wrong_extension_is_rejected(): void
    {
        Storage::fake('public');
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_PDF);

        Sanctum::actingAs($students[0]);
        $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            ['files' => [UploadedFile::fake()->create('notes.txt', 4, 'text/plain')]]
        )->assertStatus(422)->assertJsonValidationErrors('files.0');
    }

    public function test_a_non_member_cannot_submit_for_the_team(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();
        $project = app(ProjectAssignmentService::class)->assignStudent($assessment, $students[0], notify: false);
        $deliverable = $this->deliverableFor($project, ProjectDeliverable::TYPE_TEXT);

        Sanctum::actingAs($students[1]);
        $this->postJson(
            "/api/v1/projects/{$project->project_id}/deliverables/{$deliverable->project_deliverable_id}/submit",
            ['body' => 'Not my team.']
        )->assertForbidden();
    }

    public function test_project_endpoints_require_authentication(): void
    {
        [$course, $assessment, $admin, $students] = $this->fixture();

        $this->getJson("/api/v1/courses/{$course->course_id}/projects")->assertUnauthorized();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverableFor(Project $project, string $type, array $attributes = []): ProjectDeliverable
    {
        return ProjectDeliverable::create(array_merge([
            'project_id' => $project->project_id,
            'title' => 'API deliverable',
            'sort_order' => 0,
            'submission_type' => $type,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
            'is_required' => true,
            'allow_late' => true,
        ], $attributes));
    }

    /**
     * Published assessment (max 100) with two teams and two unseated students.
     *
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: list<User>}
     */
    private function fixture(): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'API Project Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'API Project Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'api-project-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => "api-project-s{$i}@example.com",
                'first_name' => 'Api',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'API Assessment',
            'min_team_size' => 1,
            'max_team_size' => 1,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 2,
        ], $admin);
        $assessment->update(['is_published' => true]);

        return [$course, $assessment->fresh('criteria'), $admin, $students];
    }
}
