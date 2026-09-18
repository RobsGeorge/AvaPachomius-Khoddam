<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectAccessVisibilityTest extends EventModuleTestCase
{
    public function test_course_admin_can_open_manage_without_navbar_course(): void
    {
        Mail::fake();
        [$course, $admin] = $this->staffFixture();
        session()->forget('current_course_id');

        $this->actingAs($admin)
            ->get(route('projects.manage'))
            ->assertOk()
            ->assertSee(__('projects.manage_title'), false);
    }

    public function test_student_index_highlights_team_change_and_alerts(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->publishedFixture();
        $student = $students[0];
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $assessment = ProjectAssessment::query()->where('course_id', $course->course_id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('projects.join', $assessment))
            ->assertRedirect();

        $membership = $assessment->activeMembershipFor((int) $student->user_id);
        $this->assertNotNull($membership);

        $this->actingAs($student)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(__('projects.change_team_here'), false)
            ->assertSee(__('projects.change_team_open_page'), false)
            ->assertSee(__('projects.student_alerts_title'), false)
            ->assertSee(__('projects.student_alert_assigned'), false);

        $this->actingAs($student)
            ->get(route('projects.show', $membership->project).'#change-team')
            ->assertOk()
            ->assertSee('id="change-team"', false)
            ->assertSee(__('projects.leave_submit'), false)
            ->assertSee(__('projects.student_alert_teammate'), false)
            ->assertSee(__('projects.student_alert_moved'), false);
    }

    /**
     * @return array{0: Course, 1: User}
     */
    private function staffFixture(): array
    {
        $course = $this->createCourse(['title' => 'Access Course', 'status' => Course::STATUS_ACTIVE]);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'prj-access-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $admin];
    }

    /**
     * @return array{0: Course, 1: Module, 2: User, 3: list<User>}
     */
    private function publishedFixture(): array
    {
        $course = $this->createCourse(['title' => 'Access Student Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Access Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $admin = $this->createUser(['email' => 'prj-access-staff@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser(['email' => "prj-access-student{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Access Ministry Project',
            'min_team_size' => 1,
            'max_team_size' => 3,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'requirements' => 'Visit a family',
        ], $admin)->update(['is_published' => true]);

        return [$course, $module, $admin, $students];
    }
}
