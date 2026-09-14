<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectTeamBriefTest extends EventModuleTestCase
{
    public function test_create_and_edit_store_four_team_brief_fields(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Youth meeting',
                'min_team_size' => 1,
                'max_team_size' => 3,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    [
                        'title' => 'Friday youth',
                        'brief_main_title' => 'Friday gathering',
                        'brief_audience' => 'Youth 15–20',
                        'brief_environment' => 'Church hall after vespers',
                        'brief_purpose' => 'Announce and host the meeting',
                    ],
                ],
            ])
            ->assertRedirect(route('projects.manage'));

        $project = Project::query()->where('title', 'Friday youth')->first();
        $this->assertNotNull($project);
        $this->assertSame('Friday gathering', $project->brief_main_title);
        $this->assertSame('Youth 15–20', $project->brief_audience);
        $this->assertSame('Church hall after vespers', $project->brief_environment);
        $this->assertSame('Announce and host the meeting', $project->brief_purpose);
        $this->assertStringContainsString('Friday gathering', (string) $project->requirements);

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'title' => 'Friday youth',
                'brief_main_title' => 'Updated main title',
                'brief_audience' => 'Servants and youth',
                'brief_environment' => 'Parish courtyard',
                'brief_purpose' => 'Invite families',
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Updated main title', $project->brief_main_title);
        $this->assertSame('Servants and youth', $project->brief_audience);
        $this->assertSame('Parish courtyard', $project->brief_environment);
        $this->assertSame('Invite families', $project->brief_purpose);
    }

    public function test_brief_fields_reject_more_than_255_characters(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $tooLong = str_repeat('a', 256);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Too long brief',
                'min_team_size' => 1,
                'max_team_size' => 2,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    [
                        'title' => 'Team A',
                        'brief_purpose' => $tooLong,
                    ],
                ],
            ])
            ->assertSessionHasErrors('subprojects.0.brief_purpose');

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Fits brief',
                'min_team_size' => 1,
                'max_team_size' => 2,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [
                    [
                        'title' => 'Team B',
                        'brief_purpose' => str_repeat('b', 255),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(255, mb_strlen((string) Project::query()->where('title', 'Team B')->value('brief_purpose')));
    }

    public function test_students_see_four_brief_fields_on_join_and_project_page(): void
    {
        Mail::fake();
        [$course, $module, $admin, $students] = $this->staffWithStudents(1);
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->post(route('projects.assessments.store'), [
                'module_id' => $module->module_id,
                'title' => 'Brief join project',
                'min_team_size' => 1,
                'max_team_size' => 3,
                'join_closes_at' => now()->addWeek()->toDateTimeString(),
                'subprojects' => [[
                    'title' => 'Hospital visit',
                    'brief_main_title' => 'Visit the ward',
                    'brief_audience' => 'Patients on floor 2',
                    'brief_environment' => 'City hospital chapel',
                    'brief_purpose' => 'Pray and listen',
                ]],
            ])
            ->assertRedirect();

        $assessment = ProjectAssessment::query()->where('title', 'Brief join project')->firstOrFail();
        $this->actingAs($admin)->post(route('projects.assessments.publish', $assessment));

        $student = $students[0];
        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);
        $this->actingAs($student)->post(route('projects.join', $assessment))->assertRedirect();

        $project = $assessment->fresh()->activeMembershipFor((int) $student->user_id)->project;
        $this->assertNotNull($project);

        $this->actingAs($student)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('projects.brief_main_title'), false)
            ->assertSee(__('projects.brief_audience'), false)
            ->assertSee(__('projects.brief_environment'), false)
            ->assertSee(__('projects.brief_purpose'), false)
            ->assertSee('Visit the ward', false)
            ->assertSee('Patients on floor 2', false)
            ->assertSee('City hospital chapel', false)
            ->assertSee('Pray and listen', false);

        $this->actingAs($student)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Visit the ward', false)
            ->assertSee('Patients on floor 2', false)
            ->assertSee('City hospital chapel', false)
            ->assertSee('Pray and listen', false);

        $notice = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'project_assigned')
            ->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Hospital visit', $notice->body);
        $this->assertStringContainsString('Visit the ward', $notice->body);
        $this->assertStringContainsString('Patients on floor 2', $notice->body);
        $this->assertStringContainsString('City hospital chapel', $notice->body);
        $this->assertStringContainsString('Pray and listen', $notice->body);
    }

    public function test_manage_form_shows_four_brief_boxes_instead_of_one_description(): void
    {
        Mail::fake();
        [$course, $module, $admin] = $this->staffFixture();
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);

        $this->actingAs($admin)
            ->get(route('projects.manage'))
            ->assertOk()
            ->assertSee(__('projects.brief_main_title'), false)
            ->assertSee(__('projects.brief_audience'), false)
            ->assertSee(__('projects.brief_environment'), false)
            ->assertSee(__('projects.brief_purpose'), false)
            ->assertDontSee('name="subprojects[0][requirements]"', false);
    }

    /**
     * @return array{0: Course, 1: Module, 2: User}
     */
    private function staffFixture(): array
    {
        $course = $this->createCourse(['title' => 'Brief Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Brief Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage']);
        $admin = $this->createUser(['email' => 'brief-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        return [$course, $module, $admin];
    }

    /**
     * @return array{0: Course, 1: Module, 2: User, 3: list<User>}
     */
    private function staffWithStudents(int $studentCount = 1): array
    {
        [$course, $module, $admin] = $this->staffFixture();
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $students = [];
        for ($i = 0; $i < $studentCount; $i++) {
            $student = $this->createUser([
                'email' => "brief-student{$i}@example.com",
                'first_name' => 'Brief',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        return [$course, $module, $admin, $students];
    }
}
