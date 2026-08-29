<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

class ProjectSubmissionTest extends EventModuleTestCase
{
    public function test_admin_creates_typed_deliverables(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Typed Deliverables',
                'min_team_size' => 1,
                'max_team_size' => 3,
                'project_count' => 1,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'deliverables' => [
                    [
                        'title' => 'Photos',
                        'submission_type' => 'image',
                        'file_mode' => 'multi',
                        'is_required' => '1',
                        'allow_late' => '0',
                        'instructions' => 'At least three photos.',
                    ],
                    [
                        'title' => 'Reflection',
                        'submission_type' => 'text',
                        'is_required' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('projects.manage'));

        $assessment = ProjectAssessment::query()->where('title', 'Typed Deliverables')->firstOrFail();
        $deliverables = $assessment->projects->first()->deliverables()->orderBy('sort_order')->get();

        $this->assertCount(2, $deliverables);
        $this->assertSame('image', $deliverables[0]->submission_type);
        $this->assertSame('multi', $deliverables[0]->file_mode);
        $this->assertTrue($deliverables[0]->is_required);
        $this->assertFalse($deliverables[0]->allow_late);
        $this->assertSame('At least three photos.', $deliverables[0]->instructions);
        $this->assertSame('text', $deliverables[1]->submission_type);
        $this->assertFalse($deliverables[1]->is_required);
    }

    public function test_unknown_submission_type_is_rejected(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Bad Deliverable',
                'min_team_size' => 1,
                'max_team_size' => 3,
                'project_count' => 1,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'deliverables' => [['title' => 'Mystery', 'submission_type' => 'spreadsheet']],
            ])
            ->assertSessionHasErrors('deliverables.0.submission_type');

        $this->assertFalse(ProjectAssessment::query()->where('title', 'Bad Deliverable')->exists());
    }

    public function test_team_member_uploads_a_file_and_sees_the_checklist(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_PDF,
        ]);

        $response = $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('report.pdf', 64, 'application/pdf')],
                'body' => 'First draft attached.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $submission = ProjectDeliverableSubmission::query()
            ->where('project_id', $project->project_id)
            ->firstOrFail();

        $this->assertSame('First draft attached.', $submission->body);
        $this->assertSame((int) $students[0]->user_id, (int) $submission->submitted_by_user_id);
        $this->assertFalse($submission->is_late);
        $this->assertCount(1, $submission->files);
        Storage::disk('public')->assertExists($submission->files->first()->file_path);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.submission_created')->exists());

        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('projects.deliverables_checklist'))
            ->assertSee(__('projects.submitted'))
            ->assertSee('report.pdf');
    }

    public function test_wrong_file_extension_is_rejected(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_PDF,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('sheet.xlsx', 10)],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_PDF,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('huge.pdf', ProjectDeliverable::MAX_UPLOAD_KB + 512, 'application/pdf')],
            ])
            ->assertSessionHasErrors('files.0');
    }

    public function test_a_student_outside_the_team_cannot_submit(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
        ]);

        app(CourseContextService::class)->setCurrentCourse($students[2], $course->course_id);
        $this->actingAs($students[2])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'not mine'])
            ->assertForbidden();

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    public function test_submissions_stay_open_after_the_join_window_closes(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
        ]);

        $project->assessment->update(['join_closes_at' => now()->subDay()]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'still allowed'])
            ->assertRedirect();

        $this->assertSame(1, ProjectDeliverableSubmission::query()->count());
    }

    public function test_member_can_remove_an_attached_file(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_IMAGE,
            'file_mode' => ProjectDeliverable::FILE_MODE_MULTI,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
            ])
            ->assertSessionHasNoErrors();

        $file = ProjectSubmissionFile::query()->firstOrFail();

        $this->actingAs($students[0])
            ->delete(route('projects.submission-files.destroy', [$project, $file]))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($file->file_path);
        $this->assertSame(1, ProjectSubmissionFile::query()->count());
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.submission_file_deleted')->exists());
    }

    public function test_link_deliverable_accepts_a_url_and_shows_it(): void
    {
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_LINK,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'link_url' => 'https://drive.example/team-folder',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('https://drive.example/team-folder');
    }

    public function test_late_submission_shows_the_late_badge(): void
    {
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'due_at' => now()->subDays(2),
            'allow_late' => true,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'late work'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(ProjectDeliverableSubmission::query()->firstOrFail()->is_late);

        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('projects.late'));
    }

    public function test_closed_deliverable_rejects_a_late_submission(): void
    {
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'due_at' => now()->subDays(2),
            'allow_late' => false,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), ['body' => 'too late'])
            ->assertSessionHasErrors('deliverable');

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    public function test_admin_saves_the_team_workspace_and_members_see_it(): void
    {
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
        ]);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.workspace.update', $project), [
                'team_workspace_url' => 'https://chat.example/team-1',
                'team_announcement' => 'Meet on Friday at 6pm.',
            ])
            ->assertRedirect();

        $this->assertSame('https://chat.example/team-1', $project->fresh()->team_workspace_url);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.workspace_updated')->exists());

        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Meet on Friday at 6pm.')
            ->assertSee(__('projects.team_workspace_open'));
    }

    public function test_invalid_workspace_url_is_rejected(): void
    {
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
        ]);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.workspace.update', $project), ['team_workspace_url' => 'chat-example'])
            ->assertSessionHasErrors('team_workspace_url');
    }

    public function test_a_team_with_submissions_cannot_be_deleted_or_have_its_deliverables_replaced(): void
    {
        Storage::fake('public');
        [$course, $admin, $students, $project, $deliverable] = $this->seatedFixture([
            'submission_type' => ProjectDeliverable::TYPE_PDF,
        ]);

        $this->actingAs($students[0])
            ->post(route('projects.deliverables.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        $path = ProjectSubmissionFile::query()->firstOrFail()->file_path;

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->delete(route('projects.destroy', $project))
            ->assertSessionHasErrors('project');

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'title' => $project->title,
                'deliverables' => [['title' => 'Replaced', 'submission_type' => 'text']],
            ])
            ->assertSessionHasErrors('deliverables');

        Storage::disk('public')->assertExists($path);
        $this->assertSame(1, ProjectDeliverableSubmission::query()->count());
        $this->assertSame(
            'Team deliverable',
            $project->fresh()->deliverables()->firstOrFail()->title
        );
    }

    /**
     * @return array{0: Course, 1: Module, 2: User}
     */
    private function staffFixture(): array
    {
        $course = $this->createCourse(['title' => 'Submission Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Submission Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'sub-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $module, $admin];
    }

    /**
     * Published assessment with one team, one seated member and one deliverable.
     *
     * @param  array<string, mixed>  $deliverableAttributes
     * @return array{0: Course, 1: User, 2: list<User>, 3: Project, 4: ProjectDeliverable}
     */
    private function seatedFixture(array $deliverableAttributes): array
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $student = $this->createUser([
                'email' => "sub-student{$i}@example.com",
                'first_name' => 'Student',
                'second_name' => 'S'.$i,
                'mobile_number' => '011000000'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Submission Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $admin);
        $assessment->update(['is_published' => true]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])->post(route('projects.join', $assessment))->assertRedirect();

        $project = $assessment->activeMembershipFor((int) $students[0]->user_id)->project;

        $deliverable = ProjectDeliverable::create(array_merge([
            'project_id' => $project->project_id,
            'title' => 'Team deliverable',
            'sort_order' => 0,
            'is_required' => true,
            'allow_late' => true,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
        ], $deliverableAttributes));

        return [$course, $admin, $students, $project->fresh(), $deliverable];
    }
}
