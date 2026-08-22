<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectResultsVisibilityService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProjectGradingTest extends EventModuleTestCase
{
    public function test_student_cannot_see_score_until_results_are_announced(): void
    {
        Mail::fake();
        [$admin, $students, $assessment, $grade] = $this->gradedProjectFixture();
        $student = $students[0];

        app(CourseContextService::class)->setCurrentCourse($student, $assessment->course_id);
        $visibility = app(ProjectResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $assessment));
        $this->assertSame('pending_announcement', $visibility->hideReason($student, $assessment));

        $this->actingAs($student)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(__('projects.score_pending_announcement'), false)
            ->assertDontSee(number_format((float) $grade->percent, 1).'%', false);

        app(CourseContextService::class)->setCurrentCourse($admin, $assessment->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment))
            ->assertRedirect();

        $assessment->refresh();
        $this->assertTrue($assessment->areResultsAnnounced());
        $this->assertTrue($visibility->canStudentViewScore($student, $assessment->fresh()));
        $this->assertTrue(ActivityLog::query()->where('route_name', 'project.results_announced')->exists());

        app(CourseContextService::class)->setCurrentCourse($student, $assessment->course_id);
        $this->actingAs($student)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(number_format((float) $grade->percent, 1).'%', false)
            ->assertSee(__('projects.passed'), false);
    }

    public function test_announced_score_stays_hidden_until_mandatory_module_survey_submitted(): void
    {
        Mail::fake();
        [$admin, $students, $assessment] = $this->gradedProjectFixture();
        $student = $students[0];

        $survey = FeedbackSurvey::create([
            'course_id' => $assessment->course_id,
            'module_id' => $assessment->module_id,
            'title' => 'End module survey',
            'created_by_user_id' => $admin->user_id,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'is_mandatory' => true,
            'opened_at' => now(),
        ]);
        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Comments',
            'order_index' => 1,
            'is_required' => true,
        ]);

        app(CourseContextService::class)->setCurrentCourse($admin, $assessment->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment))
            ->assertRedirect();

        $visibility = app(ProjectResultsVisibilityService::class);
        $this->assertFalse($visibility->canStudentViewScore($student, $assessment->fresh()));
        $this->assertSame('pending_feedback', $visibility->hideReason($student, $assessment->fresh()));

        FeedbackSubmission::create([
            'survey_id' => $survey->survey_id,
            'user_id' => $student->user_id,
            'submitted_at' => now(),
        ]);

        $this->assertTrue($visibility->canStudentViewScore($student, $assessment->fresh()));
        $this->assertSame('visible', $visibility->hideReason($student, $assessment->fresh()));
    }

    public function test_double_announce_is_rejected(): void
    {
        Mail::fake();
        [$admin, , $assessment] = $this->gradedProjectFixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $assessment->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('projects.grades.announce', $assessment->fresh()))
            ->assertStatus(409);
    }

    public function test_student_cannot_grade_or_announce(): void
    {
        Mail::fake();
        [, $students, $assessment] = $this->gradedProjectFixture();
        $student = $students[0];

        app(CourseContextService::class)->setCurrentCourse($student, $assessment->course_id);
        $this->actingAs($student)
            ->get(route('projects.grades', $assessment))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('projects.grades.announce', $assessment))
            ->assertForbidden();
    }

    public function test_admin_can_override_one_student_via_http(): void
    {
        Mail::fake();
        [$admin, $students, $assessment] = $this->gradedProjectFixture();

        app(CourseContextService::class)->setCurrentCourse($admin, $assessment->course_id);
        $this->actingAs($admin)
            ->post(route('projects.grades.student', [$assessment, $students[1]]), [
                'points' => 40,
            ])
            ->assertRedirect();

        $overridden = ProjectMemberGrade::query()
            ->where('user_id', $students[1]->user_id)
            ->first();
        $this->assertSame(40.0, (float) $overridden->points);
        $this->assertSame(40.0, (float) $overridden->percent);
        $this->assertSame(ProjectMemberGrade::SOURCE_OVERRIDE, $overridden->source);
        $this->assertFalse($overridden->passed((int) $assessment->passing_percent));
    }

    /**
     * @return array{0: \App\Models\User, 1: list<\App\Models\User>, 2: ProjectAssessment, 3: ProjectMemberGrade}
     */
    private function gradedProjectFixture(): array
    {
        $course = $this->createCourse(['title' => 'HTTP Grade Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'HTTP Grade Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);
        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', ['project.view', 'project.manage', 'project.grade']);
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['project.view', 'project.join']);
        $admin = $this->createUser(['email' => 'prj-http-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => "prj-http-s{$i}@example.com",
                'first_name' => 'Student',
                'second_name' => 'G'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'HTTP Graded Project',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'max_points' => 100,
            'passing_percent' => 50,
            'project_count' => 1,
            'criteria' => [
                ['title' => 'Content', 'max_points' => 60],
                ['title' => 'Presentation', 'max_points' => 40],
            ],
        ], $admin);
        $assessment->update(['is_published' => true]);
        $assessment->load(['criteria', 'projects']);

        $assign = app(ProjectAssignmentService::class);
        foreach ($students as $student) {
            $assign->assignStudent($assessment, $student, notify: false);
        }

        $project = $assessment->projects()->first();
        $criteria = $assessment->criteria;
        $this->actingAs($admin);
        app(CourseContextService::class)->setCurrentCourse($admin, $course->course_id);
        $this->post(route('projects.grades.team', $project), [
            'scores' => [
                $criteria[0]->project_grade_criterion_id => 48,
                $criteria[1]->project_grade_criterion_id => 32,
            ],
        ])->assertRedirect();

        $grade = ProjectMemberGrade::query()
            ->where('user_id', $students[0]->user_id)
            ->first();

        return [$admin, $students, $assessment->fresh(), $grade];
    }
}
