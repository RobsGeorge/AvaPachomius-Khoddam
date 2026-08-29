<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

/**
 * UC-PRJ-22 / UC-PRJ-23: the accepted extensions per submission type, the
 * multi-file cap, and the "one row per team, replaceable by any member" rule.
 */
class ProjectDeliverableTypeMatrixTest extends EventModuleTestCase
{
    public function test_a_document_deliverable_accepts_office_files_and_rejects_images(): void
    {
        Storage::fake('public');
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_DOCUMENT);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('brief.docx', 20)],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('brief.docx', ProjectSubmissionFile::query()->firstOrFail()->original_name);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->image('poster.png')],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(1, ProjectSubmissionFile::query()->count());
    }

    public function test_a_zip_deliverable_only_accepts_archives(): void
    {
        Storage::fake('public');
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_ZIP);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('handout.docx', 12)],
            ])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('bundle.zip', 12, 'application/zip')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('bundle.zip', ProjectSubmissionFile::query()->firstOrFail()->original_name);
    }

    public function test_a_single_file_deliverable_refuses_a_second_file_in_one_post(): void
    {
        Storage::fake('public');
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_PDF);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [
                    UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
                    UploadedFile::fake()->create('b.pdf', 8, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    public function test_a_multi_file_deliverable_stops_at_the_ten_file_cap(): void
    {
        Storage::fake('public');
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_IMAGE, [
            'file_mode' => ProjectDeliverable::FILE_MODE_MULTI,
        ]);

        $tooMany = [];
        for ($i = 0; $i <= ProjectDeliverable::MAX_FILES; $i++) {
            $tooMany[] = UploadedFile::fake()->image("shot-{$i}.png");
        }

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['files' => $tooMany])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, ProjectSubmissionFile::query()->count());

        $accepted = array_slice($tooMany, 0, ProjectDeliverable::MAX_FILES);
        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['files' => $accepted])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectDeliverable::MAX_FILES, ProjectSubmissionFile::query()->count());
    }

    public function test_a_teammate_replaces_the_single_team_submission(): void
    {
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_TEXT);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'First draft.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($students[1])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'Final answer.'])
            ->assertSessionHasNoErrors();

        $submissions = ProjectDeliverableSubmission::query()
            ->where('project_id', $project->project_id)
            ->get();

        $this->assertCount(1, $submissions, 'One submission row per team per deliverable.');
        $this->assertSame('Final answer.', $submissions->first()->body);
        $this->assertSame((int) $students[1]->user_id, (int) $submissions->first()->submitted_by_user_id);
    }

    public function test_a_link_deliverable_replaces_the_stored_url(): void
    {
        [$project, $students] = $this->fixture();
        $deliverable = $this->deliverable($project, ProjectDeliverable::TYPE_LINK);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'link_url' => 'https://drive.example.com/first',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($students[1])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'link_url' => 'https://drive.example.com/second',
            ])
            ->assertSessionHasNoErrors();

        $submission = ProjectDeliverableSubmission::query()->firstOrFail();
        $this->assertSame('https://drive.example.com/second', $submission->link_url);
        $this->assertSame(1, ProjectDeliverableSubmission::query()->count());
        $this->assertCount(0, $submission->files);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverable(Project $project, string $type, array $attributes = []): ProjectDeliverable
    {
        return ProjectDeliverable::create(array_merge([
            'project_id' => $project->project_id,
            'title' => 'Typed deliverable',
            'sort_order' => 0,
            'submission_type' => $type,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
            'is_required' => true,
            'allow_late' => true,
        ], $attributes));
    }

    /**
     * One published team with two seated members.
     *
     * @return array{0: Project, 1: list<User>}
     */
    private function fixture(): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Deliverable Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Deliverable Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'deliverable-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "deliverable-s{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Deliverable Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);

        $assign = app(ProjectAssignmentService::class);
        $project = $assign->assignStudent($assessment, $students[0], notify: false);
        $assign->assignStudent($assessment, $students[1], notify: false);

        foreach ($students as $student) {
            app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        }

        return [$project->fresh(), $students];
    }
}
