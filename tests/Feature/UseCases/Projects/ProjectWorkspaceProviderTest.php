<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class ProjectWorkspaceProviderTest extends EventModuleTestCase
{
    public function test_admin_saves_provider_and_matching_url(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.workspace.update', $project), [
                'workspace_provider' => Project::WORKSPACE_DRIVE,
                'team_workspace_url' => 'https://drive.google.com/drive/folders/abc',
                'team_announcement' => 'Shared folder',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $project->refresh();
        $this->assertSame(Project::WORKSPACE_DRIVE, $project->workspace_provider);
        $this->assertSame('https://drive.google.com/drive/folders/abc', $project->team_workspace_url);

        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('projects.workspace_provider_drive'))
            ->assertSee(__('projects.team_workspace_open'));
    }

    public function test_provider_host_mismatch_is_rejected(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.workspace.update', $project), [
                'workspace_provider' => Project::WORKSPACE_WHATSAPP,
                'team_workspace_url' => 'https://drive.google.com/file/d/1',
            ])
            ->assertSessionHasErrors('team_workspace_url');
    }

    public function test_student_without_manage_cannot_update_workspace(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.workspace.update', $project), [
                'workspace_provider' => Project::WORKSPACE_CUSTOM,
                'team_workspace_url' => 'https://example.com/team',
            ])
            ->assertForbidden();
    }

    public function test_api_show_includes_workspace_provider(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project] = $this->fixture();
        $project->update([
            'workspace_provider' => Project::WORKSPACE_TELEGRAM,
            'team_workspace_url' => 'https://t.me/+abc',
        ]);

        Sanctum::actingAs($students[0]);
        $this->getJson('/api/v1/projects/'.$project->project_id)
            ->assertOk()
            ->assertJsonPath('data.workspace_provider', Project::WORKSPACE_TELEGRAM)
            ->assertJsonPath('data.team_workspace_url', 'https://t.me/+abc');
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: Project}
     */
    private function fixture(): array
    {
        $course = $this->createCourse(['title' => 'Workspace Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Workspace Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage',
        ]);
        $admin = $this->createUser(['email' => 'ws-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $student = $this->createUser(['email' => 'ws-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Workspace Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);

        $project = $assessment->projects()->firstOrFail();
        ProjectMembership::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'user_id' => $student->user_id,
            'status' => ProjectMembership::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'N/A',
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
        ]);

        return [$course, $admin, [$student], $project->fresh()];
    }
}
