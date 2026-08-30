<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

/**
 * Team projects are gated on the church's `projects` capability (master-plan §8).
 * A church without it must not see the feature at all — web and mobile API alike.
 */
class ProjectCapabilityGateTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_the_web_project_routes_are_hidden_without_the_capability(): void
    {
        [$course, $assessment, $admin, $student] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)->get(route('projects.index'))->assertOk();

        $this->disableProjectsCapability();

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)->get(route('projects.index'))->assertNotFound();
        $this->actingAs($student)->post(route('projects.join', $assessment))->assertNotFound();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)->get(route('projects.manage'))->assertNotFound();

        $this->assertNull($assessment->fresh()->activeMembershipFor((int) $student->user_id));
    }

    public function test_the_api_project_routes_are_hidden_without_the_capability(): void
    {
        [$course, $assessment, $admin, $student] = $this->fixture();

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/courses/{$course->course_id}/projects")->assertOk();

        $this->disableProjectsCapability();

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/courses/{$course->course_id}/projects")->assertNotFound();
        $this->postJson("/api/v1/project-assessments/{$assessment->project_assessment_id}/join")
            ->assertNotFound();
    }

    public function test_re_enabling_the_capability_restores_access(): void
    {
        [$course, $assessment, $admin, $student] = $this->fixture();

        $this->disableProjectsCapability();
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)->get(route('projects.index'))->assertNotFound();

        $this->setProjectsCapability(true);
        $this->assertTrue(Church::main()->fresh()->hasCapability('projects'));

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)->get(route('projects.index'))->assertOk();
    }

    private function disableProjectsCapability(): void
    {
        $this->setProjectsCapability(false);
        $this->assertFalse(Church::main()->fresh()->hasCapability('projects'));
    }

    private function setProjectsCapability(bool $enabled): void
    {
        ChurchCapability::query()
            ->where('church_id', Church::main()->church_id)
            ->where('capability_key', 'projects')
            ->update(['enabled' => $enabled]);
    }

    /**
     * @return array{0: Course, 1: ProjectAssessment, 2: User, 3: User}
     */
    private function fixture(): array
    {
        Mail::fake();

        $course = $this->createCourse(['title' => 'Capability Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Capability Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'capability-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $student = $this->createUser(['email' => 'capability-student@example.com']);
        $this->assignCourseRole($student, $course, $studentRole);

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Capability Assessment',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);

        return [$course, $assessment->fresh(), $admin, $student];
    }
}
