<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\ProjectAdminService;
use App\Services\ProjectSubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ProjectSubmissionServiceTest extends EventModuleTestCase
{
    public function test_pdf_deliverable_stores_one_file_and_replaces_it_on_resubmit(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_PDF]);

        $service = app(ProjectSubmissionService::class);
        $first = $service->submit($project, $deliverable, $student, [], [
            UploadedFile::fake()->create('draft.pdf', 40, 'application/pdf'),
        ]);

        $this->assertCount(1, $first->files);
        Storage::disk('public')->assertExists($first->files->first()->file_path);
        $firstPath = $first->files->first()->file_path;

        $second = $service->submit($project, $deliverable, $student, [], [
            UploadedFile::fake()->create('final.pdf', 40, 'application/pdf'),
        ]);

        $this->assertSame(
            $first->project_deliverable_submission_id,
            $second->project_deliverable_submission_id
        );
        $this->assertCount(1, $second->files);
        $this->assertSame('final.pdf', $second->files->first()->original_name);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_single_file_deliverable_rejects_multiple_uploads(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_IMAGE,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
        ]);

        $this->expectException(ValidationException::class);
        app(ProjectSubmissionService::class)->submit($project, $deliverable, $student, [], [
            UploadedFile::fake()->image('one.png'),
            UploadedFile::fake()->image('two.png'),
        ]);
    }

    public function test_multi_file_deliverable_appends_until_the_cap(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_IMAGE,
            'file_mode' => ProjectDeliverable::FILE_MODE_MULTI,
        ]);

        $service = app(ProjectSubmissionService::class);
        $service->submit($project, $deliverable, $student, [], [UploadedFile::fake()->image('a.png')]);
        $submission = $service->submit($project, $deliverable, $student, [], [UploadedFile::fake()->image('b.png')]);

        $this->assertCount(2, $submission->files);

        $tooMany = [];
        for ($i = 0; $i < ProjectDeliverable::MAX_FILES; $i++) {
            $tooMany[] = UploadedFile::fake()->image("extra-{$i}.png");
        }

        $this->expectException(ValidationException::class);
        $service->submit($project, $deliverable, $student, [], $tooMany);
    }

    public function test_multi_file_deliverable_can_replace_the_whole_set(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_IMAGE,
            'file_mode' => ProjectDeliverable::FILE_MODE_MULTI,
        ]);

        $service = app(ProjectSubmissionService::class);
        $service->submit($project, $deliverable, $student, [], [UploadedFile::fake()->image('a.png')]);
        $submission = $service->submit($project, $deliverable, $student, ['replace_files' => true], [
            UploadedFile::fake()->image('b.png'),
        ]);

        $this->assertCount(1, $submission->files);
        $this->assertSame('b.png', $submission->files->first()->original_name);
    }

    public function test_link_deliverable_requires_a_valid_url(): void
    {
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_LINK]);
        $service = app(ProjectSubmissionService::class);

        try {
            $service->submit($project, $deliverable, $student, ['link_url' => 'not-a-url']);
            $this->fail('Expected a validation error for an invalid link.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('link_url', $e->errors());
        }

        $submission = $service->submit($project, $deliverable, $student, ['link_url' => 'https://drive.example/team']);
        $this->assertSame('https://drive.example/team', $submission->link_url);
        $this->assertCount(0, $submission->files);
    }

    public function test_text_deliverable_requires_a_body(): void
    {
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_TEXT]);
        $service = app(ProjectSubmissionService::class);

        try {
            $service->submit($project, $deliverable, $student, ['body' => '   ']);
            $this->fail('Expected a validation error for an empty answer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->errors());
        }

        $submission = $service->submit($project, $deliverable, $student, ['body' => 'Our written answer.']);
        $this->assertSame('Our written answer.', $submission->body);
    }

    public function test_file_deliverable_requires_at_least_one_file(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_PDF]);

        $this->expectException(ValidationException::class);
        app(ProjectSubmissionService::class)->submit($project, $deliverable, $student, ['body' => 'no file']);
    }

    public function test_submission_after_the_due_date_is_flagged_late(): void
    {
        [$project, $deliverable, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'due_at' => now()->subDay(),
            'allow_late' => true,
        ]);

        $submission = app(ProjectSubmissionService::class)
            ->submit($project, $deliverable, $student, ['body' => 'late but accepted']);

        $this->assertTrue($submission->is_late);
    }

    public function test_late_submission_is_blocked_when_allow_late_is_off(): void
    {
        [$project, $deliverable, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'due_at' => now()->subDay(),
            'allow_late' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(ProjectSubmissionService::class)->submit($project, $deliverable, $student, ['body' => 'too late']);
    }

    public function test_progress_counts_only_required_deliverables_as_missing(): void
    {
        [$project, $required, $student] = $this->fixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'is_required' => true,
        ]);

        ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'Optional extra',
            'sort_order' => 1,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'is_required' => false,
        ]);

        $service = app(ProjectSubmissionService::class);
        $progress = $service->progress($project->fresh());
        $this->assertSame(1, $progress['required']);
        $this->assertSame(1, $progress['missing']);
        $this->assertSame(0, $progress['submitted']);

        $service->submit($project, $required, $student, ['body' => 'done']);

        $progress = $service->progress($project->fresh());
        $this->assertSame(0, $progress['missing']);
        $this->assertSame(1, $progress['submitted']);
    }

    public function test_deleting_a_project_removes_its_submissions_and_files(): void
    {
        Storage::fake('public');
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_PDF]);

        $service = app(ProjectSubmissionService::class);
        $submission = $service->submit($project, $deliverable, $student, [], [
            UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        ]);
        $path = $submission->files->first()->file_path;

        $service->deleteForProject($project);

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
        $this->assertSame(0, ProjectSubmissionFile::query()->count());
    }

    public function test_admin_cannot_replace_deliverables_once_a_team_submitted(): void
    {
        [$project, $deliverable, $student] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_TEXT]);
        app(ProjectSubmissionService::class)->submit($project, $deliverable, $student, ['body' => 'submitted']);

        $this->expectException(ValidationException::class);
        app(ProjectAdminService::class)->updateProject($project, [
            'deliverables' => [['title' => 'Replaced', 'submission_type' => 'text']],
        ]);
    }

    public function test_deliverable_type_must_be_known(): void
    {
        [$project] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_TEXT]);

        $this->expectException(ValidationException::class);
        app(ProjectAdminService::class)->updateProject($project, [
            'deliverables' => [['title' => 'Bad type', 'submission_type' => 'spreadsheet']],
        ]);
    }

    public function test_workspace_url_must_be_a_valid_url(): void
    {
        [$project] = $this->fixture(['submission_type' => ProjectDeliverable::TYPE_TEXT]);

        $this->expectException(ValidationException::class);
        app(ProjectAdminService::class)->updateTeamWorkspace($project, ['team_workspace_url' => 'drive']);
    }

    /**
     * Builds a published assessment with one team, one member, and one deliverable.
     *
     * @param  array<string, mixed>  $deliverableAttributes
     * @return array{0: Project, 1: ProjectDeliverable, 2: User}
     */
    private function fixture(array $deliverableAttributes): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Submission Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Submission Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $creator = $this->createUser(['email' => 'sub-admin@example.com']);
        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Submission Assessment',
            'min_team_size' => 1,
            'max_team_size' => 3,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $creator);

        $project = $assessment->projects()->firstOrFail();
        $student = $this->createUser(['email' => 'sub-student@example.com']);

        $deliverable = ProjectDeliverable::create(array_merge([
            'project_id' => $project->project_id,
            'title' => 'Team report',
            'sort_order' => 0,
            'is_required' => true,
            'allow_late' => true,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
        ], $deliverableAttributes));

        return [$project->fresh(), $deliverable, $student];
    }
}
