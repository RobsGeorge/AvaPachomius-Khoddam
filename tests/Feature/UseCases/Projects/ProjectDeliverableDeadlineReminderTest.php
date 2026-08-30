<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationScannerService;
use App\Services\ProjectAdminService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectDeliverableDeadlineReminderTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reminds_active_members_when_deliverable_is_due_within_lead_window(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));

        [$course, $students, $project, $deliverable] = $this->fixture(dueInHours: 12);

        $count = app(NotificationScannerService::class)->scanProjectDeliverableDeadlines();

        $this->assertGreaterThan(0, $count);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_deliverable_deadline')
                ->where('dedupe_key', "project_deliverable_deadline:{$deliverable->project_deliverable_id}:user:{$students[0]->user_id}:2026-08-30")
                ->exists()
        );
        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_deliverable_deadline')
                ->count()
        );

        // Second scan same day is a no-op for counts (updateOrCreate).
        $again = app(NotificationScannerService::class)->scanProjectDeliverableDeadlines();
        $this->assertSame(1, $again);
        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_deliverable_deadline')
                ->count()
        );
    }

    public function test_skips_when_team_already_submitted(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));

        [$course, $students, $project, $deliverable] = $this->fixture(dueInHours: 12);

        ProjectDeliverableSubmission::create([
            'project_assessment_id' => $project->project_assessment_id,
            'project_id' => $project->project_id,
            'project_deliverable_id' => $deliverable->project_deliverable_id,
            'submitted_by_user_id' => $students[0]->user_id,
            'body' => 'Done',
            'submitted_at' => now(),
            'is_late' => false,
        ]);

        $count = app(NotificationScannerService::class)->scanProjectDeliverableDeadlines();

        $this->assertSame(0, $count);
        $this->assertFalse(
            UserNotification::query()->where('type', 'project_deliverable_deadline')->exists()
        );
    }

    public function test_skips_unpublished_assessment_and_cancelled_team(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));

        [$course, $students, $project, $deliverable] = $this->fixture(dueInHours: 12);
        $project->assessment->update(['is_published' => false]);

        $this->assertSame(0, app(NotificationScannerService::class)->scanProjectDeliverableDeadlines());

        $project->assessment->update(['is_published' => true]);
        $project->update(['cancelled_at' => now(), 'status' => Project::STATUS_CLOSED]);

        $this->assertSame(0, app(NotificationScannerService::class)->scanProjectDeliverableDeadlines());
    }

    public function test_skips_when_due_outside_lead_window(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));

        $this->fixture(dueInHours: 48);

        $this->assertSame(0, app(NotificationScannerService::class)->scanProjectDeliverableDeadlines());
    }

    public function test_type_is_registered_in_notifications_config(): void
    {
        $this->assertArrayHasKey('project_deliverable_deadline', config('notifications.types'));
        $this->assertContains(
            'project_deliverable_deadline',
            config('notifications.categories.academic')
        );
        $this->assertSame(
            24,
            config('notifications.types.project_deliverable_deadline.defaults.config.lead_hours')
        );
    }

    public function test_scan_deadlines_includes_project_deliverable_reminders(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00'));

        [$course, $students, $project, $deliverable] = $this->fixture(dueInHours: 6);

        $count = app(NotificationScannerService::class)->scanDeadlines();

        $this->assertGreaterThan(0, $count);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $students[0]->user_id)
                ->where('type', 'project_deliverable_deadline')
                ->exists()
        );
    }

    /**
     * @return array{0: Course, 1: list<User>, 2: Project, 3: ProjectDeliverable}
     */
    private function fixture(int $dueInHours): array
    {
        $course = $this->createCourse(['title' => 'Reminder Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Reminder Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.join',
        ]);
        $admin = $this->createUser(['email' => 'reminder-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $student = $this->createUser([
            'email' => 'reminder-student@example.com',
            'first_name' => 'Reminder',
            'second_name' => 'Student',
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Reminder Assessment',
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
            'title' => 'Draft report',
            'due_at' => now()->addHours($dueInHours),
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_TEXT,
            'is_required' => true,
            'allow_late' => true,
        ]);

        return [$course, [$student], $project->fresh(), $deliverable];
    }
}
