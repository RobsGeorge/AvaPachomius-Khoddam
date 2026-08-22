<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Tenancy\TenantContext;
use App\Services\ProjectAdminService;
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
        ], $adminB);

        $this->assertNull(ProjectAssessment::find($assessmentA->project_assessment_id));
        $this->assertNotNull(ProjectAssessment::find($assessmentB->project_assessment_id));

        TenantContext::set($churchA);
        $this->assertNotNull(ProjectAssessment::find($assessmentA->project_assessment_id));
        $this->assertNull(ProjectAssessment::find($assessmentB->project_assessment_id));
    }
}
