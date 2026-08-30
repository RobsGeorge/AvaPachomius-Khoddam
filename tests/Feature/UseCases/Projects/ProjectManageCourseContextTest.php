<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\CourseContextService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectManageCourseContextTest extends EventModuleTestCase
{
    public function test_manage_locks_course_when_navbar_context_is_set(): void
    {
        Mail::fake();
        [$course, $admin] = $this->fixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->actingAs($admin)
            ->get(route('projects.manage'))
            ->assertOk()
            ->assertSee(__('projects.course_locked_help'), false)
            ->assertSee('type="hidden" name="course_id"', false)
            ->assertDontSee('course_select_placeholder', false);
    }

    public function test_store_forces_current_course_even_if_another_id_posted(): void
    {
        Mail::fake();
        [$course, $admin] = $this->fixture();
        $other = $this->createCourse(['title' => 'Other Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Ctx Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'course_id' => $other->course_id,
                'module_id' => $module->module_id,
                'title' => 'Forced Course Assessment',
                'min_team_size' => 2,
                'max_team_size' => 3,
                'join_closes_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'subprojects' => [
                    ['title' => 'Team Alpha'],
                    ['title' => 'Team Beta'],
                ],
            ])
            ->assertRedirect(route('projects.manage'));

        $this->assertDatabaseHas('project_assessments', [
            'title' => 'Forced Course Assessment',
            'course_id' => $course->course_id,
        ]);
    }

    /**
     * @return array{0: Course, 1: User}
     */
    private function fixture(): array
    {
        $course = $this->createCourse(['title' => 'Ctx Course', 'status' => Course::STATUS_ACTIVE]);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'proj-ctx-admin-'.uniqid().'@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $admin];
    }
}
