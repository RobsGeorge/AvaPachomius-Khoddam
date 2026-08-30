<?php

namespace Tests\Feature\UseCases\Projects;

use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectPeerRating;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\ProjectAdminService;
use App\Services\ProjectPeerEvaluationService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class ProjectPeerEvaluationTest extends EventModuleTestCase
{
    public function test_student_rates_teammates_anonymously_without_touching_member_grades(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->fixture();

        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
            'peer_eval_scale_max' => 5,
        ]);

        $before = ProjectMemberGrade::query()->count();

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $project), [
                'ratings' => [
                    ['ratee_user_id' => $students[1]->user_id, 'score' => 4, 'comment' => 'Great'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, ProjectPeerRating::query()->count());
        $this->assertSame($before, ProjectMemberGrade::query()->count());

        $averages = app(ProjectPeerEvaluationService::class)->adminAverages($project);
        $row = collect($averages)->firstWhere('user_id', (int) $students[1]->user_id);
        $this->assertEqualsWithDelta(4.0, (float) $row['average'], 0.01);
        $this->assertArrayNotHasKey('rater_user_id', $row);
    }

    public function test_cannot_rate_self_or_non_teammate(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->fixture();
        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($students[0], $course->course_id);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $project), [
                'ratings' => [
                    ['ratee_user_id' => $students[0]->user_id, 'score' => 5],
                ],
            ])
            ->assertSessionHasErrors('ratings');

        $outsider = $this->createUser(['email' => 'peer-outsider@example.com']);
        $this->actingAs($students[0])
            ->post(route('projects.peer-ratings.store', $project), [
                'ratings' => [
                    ['ratee_user_id' => $outsider->user_id, 'score' => 3],
                ],
            ])
            ->assertSessionHasErrors('ratings');
    }

    public function test_api_exposes_only_own_pending_ratings(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->fixture();
        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($students[0]);
        $this->getJson('/api/v1/projects/'.$project->project_id.'/peer-ratings/pending')
            ->assertOk()
            ->assertJsonPath('data.open', true)
            ->assertJsonCount(1, 'data.pending')
            ->assertJsonPath('data.pending.0.user_id', (int) $students[1]->user_id)
            ->assertJsonMissing(['rater_user_id']);
    }

    public function test_peer_ratings_are_tenant_scoped(): void
    {
        Mail::fake();
        [$course, $admin, $students, $assessment, $project] = $this->fixture();
        $assessment->update([
            'peer_eval_enabled' => true,
            'peer_eval_opens_at' => now()->subHour(),
            'peer_eval_closes_at' => now()->addDay(),
        ]);

        app(ProjectPeerEvaluationService::class)->submitRatings($project, $students[0], [
            ['ratee_user_id' => $students[1]->user_id, 'score' => 5],
        ]);

        $rating = ProjectPeerRating::query()->firstOrFail();
        $this->assertNotNull($rating->church_id);

        $otherChurch = $this->createChurch(['slug' => 'peer-other-church']);
        app(TenantContext::class)->set($otherChurch);

        $this->assertSame(
            0,
            ProjectPeerRating::query()->whereKey($rating->project_peer_rating_id)->count()
        );
    }

    /**
     * @return array{0: Course, 1: User, 2: list<User>, 3: \App\Models\ProjectAssessment, 4: \App\Models\Project}
     */
    private function fixture(): array
    {
        $course = $this->createCourse(['title' => 'Peer Course', 'status' => Course::STATUS_ACTIVE]);
        $module = Module::create(['title' => 'Peer Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id);

        $adminRole = $this->courseRoleWithPermissions($course, 'instructor', [
            'project.view', 'project.manage', 'project.grade',
        ]);
        $admin = $this->createUser(['email' => 'peer-admin@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $studentRole = $this->courseRoleWithPermissions($course, 'student', [
            'project.view', 'project.join',
        ]);
        $students = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->createUser([
                'email' => "peer-student{$i}@example.com",
                'first_name' => 'Peer',
                'second_name' => 'S'.$i,
            ]);
            $this->assignCourseRole($student, $course, $studentRole);
            $students[] = $student;
        }

        $assessment = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Peer Assessment',
            'min_team_size' => 1,
            'max_team_size' => 3,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $admin);
        $assessment->update(['is_published' => true]);
        $project = $assessment->projects()->firstOrFail();

        foreach ($students as $student) {
            ProjectMembership::create([
                'project_assessment_id' => $assessment->project_assessment_id,
                'project_id' => $project->project_id,
                'user_id' => $student->user_id,
                'status' => ProjectMembership::STATUS_ACTIVE,
                'assigned_at' => now(),
            ]);
        }

        return [$course, $admin, $students, $assessment->fresh(), $project->fresh()];
    }
}
