<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectSubmissionReviewTest extends EventModuleTestCase
{
    public function test_grader_saves_feedback_and_notifies_once(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project, $submission] = $this->reviewFixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->post(route('projects.submissions.review', [$project, $submission]), [
                'instructor_feedback' => 'Please cite your sources.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame('Please cite your sources.', $submission->instructor_feedback);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame((int) $admin->user_id, (int) $submission->reviewed_by_user_id);
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.submission_reviewed')->exists());
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_submission_feedback')
                ->exists()
        );

        UserNotification::query()->where('type', 'project_submission_feedback')->delete();

        $this->actingAs($admin)
            ->post(route('projects.submissions.review', [$project, $submission]), [
                'instructor_feedback' => 'Updated notes.',
            ])
            ->assertRedirect();

        $this->assertFalse(
            UserNotification::query()->where('type', 'project_submission_feedback')->exists()
        );
        $this->assertSame('Updated notes.', $submission->fresh()->instructor_feedback);
    }

    public function test_students_see_feedback_on_team_page(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project, $submission] = $this->reviewFixture();
        $submission->update([
            'instructor_feedback' => 'Solid draft.',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $admin->user_id,
        ]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('projects.instructor_feedback'))
            ->assertSee('Solid draft.');
    }

    public function test_student_without_grade_permission_cannot_review(): void
    {
        Mail::fake();
        [$course, $admin, $students, $project, $submission] = $this->reviewFixture();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.submissions.review', [$project, $submission]), [
                'instructor_feedback' => 'Nope',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: Project, 4: ProjectDeliverableSubmission}
     */
    private function reviewFixture(): array
    {
        $course = $this->createCourse(['title' => 'Review Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Review Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'review-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $student = $this->createUser([
            'email' => 'review-student@example.com',
            'first_name' => 'Rev',
            'second_name' => 'Student',
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Review Assessment',
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

        $deliverable = ProjectDeliverable::create([
            'project_id' => $project->project_id,
            'title' => 'Draft',
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'is_required' => true,
            'allow_late' => true,
        ]);

        $submission = ProjectDeliverableSubmission::create([
            'project_assessment_id' => $assessment->project_assessment_id,
            'project_id' => $project->project_id,
            'project_deliverable_id' => $deliverable->project_deliverable_id,
            'submitted_by_user_id' => $student->user_id,
            'body' => 'First draft',
            'submitted_at' => now(),
            'is_late' => false,
        ]);

        return [$course, $admin, [$student], $project->fresh(), $submission];
    }
}
