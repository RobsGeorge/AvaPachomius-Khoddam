<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectMemberGrade;
use App\Models\ProjectMembership;
use App\Models\ProjectSubmissionFile;
use App\Models\ProjectTeamGrade;
use App\Tenancy\TenantContext;
use App\Services\ProjectAdminService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectGradingService;
use App\Services\ProjectSubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

class ProjectIsolationTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_project_assessments_are_scoped_by_church(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'prj-isol-b', 'name' => 'Project B', 'status' => 'active']);

        TenantContext::set($churchA);
        $courseA = $this->createCourse(['title' => 'PRJ_A', 'church_id' => $churchA->church_id]);
        $moduleA = Module::create(['title' => 'Mod A', 'description' => 'A']);
        $courseA->modules()->attach($moduleA->module_id);
        $adminA = $this->createUser(['email' => 'prj-isol-a@example.com']);
        $assessmentA = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $courseA->course_id,
            'module_id' => $moduleA->module_id,
            'title' => 'ISO_PRJ_A',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $adminA);
        $this->assertSame((int) $churchA->church_id, (int) $assessmentA->church_id);
        $this->assertSame((int) $churchA->church_id, (int) $assessmentA->projects->first()->church_id);

        TenantContext::set($churchB);
        $courseB = $this->createCourse(['title' => 'PRJ_B', 'church_id' => $churchB->church_id]);
        $moduleB = Module::create(['title' => 'Mod B', 'description' => 'B']);
        $courseB->modules()->attach($moduleB->module_id);
        $adminB = $this->createUser(['email' => 'prj-isol-b@example.com']);
        $assessmentB = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $courseB->course_id,
            'module_id' => $moduleB->module_id,
            'title' => 'ISO_PRJ_B',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $adminB);

        $this->assertNull(ProjectAssessment::find($assessmentA->project_assessment_id));
        $this->assertNotNull(ProjectAssessment::find($assessmentB->project_assessment_id));

        TenantContext::set($churchA);
        $this->assertNotNull(ProjectAssessment::find($assessmentA->project_assessment_id));
        $this->assertNull(ProjectAssessment::find($assessmentB->project_assessment_id));
    }

    public function test_project_memberships_and_seating_flags_are_scoped_by_church(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'prj-seat-isol-b', 'name' => 'Seat B', 'status' => 'active']);

        TenantContext::set($churchA);
        $courseA = $this->createCourse(['title' => 'PRJ_SA', 'church_id' => $churchA->church_id]);
        $moduleA = Module::create(['title' => 'Mod SA', 'description' => 'A']);
        $courseA->modules()->attach($moduleA->module_id);
        $adminA = $this->createUser(['email' => 'prj-seat-isol-a@example.com']);
        $studentA = $this->createUser(['email' => 'prj-seat-student-a@example.com']);
        $assessmentA = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $courseA->course_id,
            'module_id' => $moduleA->module_id,
            'title' => 'ISO_SEAT_A',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 2,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
            'seed_pool_size' => 1,
        ], $adminA);

        $assignments = app(ProjectAssignmentService::class);
        $seated = $assignments->assignStudent($assessmentA, $studentA, notify: false);
        $assignments->lockTeam($seated, $adminA);
        $membershipId = (int) $assessmentA->activeMembershipFor((int) $studentA->user_id)->project_membership_id;

        $this->assertSame((int) $churchA->church_id, (int) $seated->church_id);
        $this->assertTrue($seated->fresh()->isLocked());

        TenantContext::set($churchB);
        $this->assertNull(ProjectMembership::find($membershipId));
        $this->assertNull(Project::find($seated->project_id));
        $this->assertSame(0, ProjectMembership::query()->count());

        TenantContext::set($churchA);
        $this->assertNotNull(ProjectMembership::find($membershipId));
        $this->assertNotNull(Project::find($seated->project_id));
    }

    public function test_project_submissions_and_files_are_scoped_by_church(): void
    {
        Storage::fake('public');

        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'prj-sub-isol-b', 'name' => 'Sub B', 'status' => 'active']);

        TenantContext::set($churchA);
        $courseA = $this->createCourse(['title' => 'PRJ_UA', 'church_id' => $churchA->church_id]);
        $moduleA = Module::create(['title' => 'Mod UA', 'description' => 'A']);
        $courseA->modules()->attach($moduleA->module_id);
        $adminA = $this->createUser(['email' => 'prj-sub-isol-a@example.com']);
        $studentA = $this->createUser(['email' => 'prj-sub-student-a@example.com']);
        $assessmentA = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $courseA->course_id,
            'module_id' => $moduleA->module_id,
            'title' => 'ISO_SUB_A',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $adminA);

        $projectA = $assessmentA->projects()->firstOrFail();
        $deliverableA = ProjectDeliverable::create([
            'project_id' => $projectA->project_id,
            'title' => 'Report',
            'sort_order' => 0,
            'submission_type' => ProjectDeliverable::TYPE_PDF,
            'file_mode' => ProjectDeliverable::FILE_MODE_SINGLE,
        ]);

        $submissionA = app(ProjectSubmissionService::class)->submit(
            $projectA,
            $deliverableA,
            $studentA,
            [],
            [UploadedFile::fake()->create('iso.pdf', 12, 'application/pdf')]
        );
        $fileIdA = (int) $submissionA->files->first()->project_submission_file_id;

        $this->assertSame((int) $churchA->church_id, (int) $submissionA->church_id);
        $this->assertSame((int) $churchA->church_id, (int) $submissionA->files->first()->church_id);

        TenantContext::set($churchB);
        $this->assertNull(ProjectDeliverableSubmission::find($submissionA->project_deliverable_submission_id));
        $this->assertNull(ProjectSubmissionFile::find($fileIdA));
        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
        $this->assertSame(0, ProjectSubmissionFile::query()->count());

        TenantContext::set($churchA);
        $this->assertNotNull(ProjectDeliverableSubmission::find($submissionA->project_deliverable_submission_id));
        $this->assertNotNull(ProjectSubmissionFile::find($fileIdA));
    }

    public function test_project_grades_are_scoped_by_church(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'prj-grade-isol-b', 'name' => 'Grade B', 'status' => 'active']);

        TenantContext::set($churchA);
        $courseA = $this->createCourse(['title' => 'PRJ_GA', 'church_id' => $churchA->church_id]);
        $moduleA = Module::create(['title' => 'Mod GA', 'description' => 'A']);
        $courseA->modules()->attach($moduleA->module_id);
        $adminA = $this->createUser(['email' => 'prj-grade-isol-a@example.com']);
        $assessmentA = app(ProjectAdminService::class)->createAssessment([
            'course_id' => $courseA->course_id,
            'module_id' => $moduleA->module_id,
            'title' => 'ISO_GRADE_A',
            'min_team_size' => 1,
            'max_team_size' => 2,
            'project_count' => 1,
            'join_closes_at' => now()->addWeek()->toDateTimeString(),
        ], $adminA);
        $grading = app(ProjectGradingService::class);
        $assessmentA = $grading->syncCriteria($assessmentA, [
            ['title' => 'Work', 'max_points' => 10],
        ]);
        $projectA = $assessmentA->projects()->first();
        $criterionA = $assessmentA->criteria->first();
        $grading->gradeTeam($assessmentA, $projectA, [
            $criterionA->project_grade_criterion_id => 8,
        ], $adminA);

        TenantContext::set($churchB);
        $this->assertNull(ProjectGradeCriterion::find($criterionA->project_grade_criterion_id));
        $this->assertNull(ProjectTeamGrade::query()->where('project_id', $projectA->project_id)->first());
        $this->assertSame(0, ProjectMemberGrade::query()->count());

        TenantContext::set($churchA);
        $this->assertNotNull(ProjectGradeCriterion::find($criterionA->project_grade_criterion_id));
        $this->assertNotNull(ProjectTeamGrade::query()->where('project_id', $projectA->project_id)->first());
    }
}
