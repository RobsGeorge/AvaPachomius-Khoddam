<?php

namespace Tests\Feature\Console;

use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Services\ProjectExampleSeedService;
use Tests\Support\EventModuleTestCase;

class SeedExampleProjectCommandTest extends EventModuleTestCase
{
    public function test_seeds_three_teams_with_distinct_titles_and_requirements(): void
    {
        $course = $this->createCourse(['title' => 'Example Seed Course', 'status' => 'active']);
        $module = Module::create(['title' => 'Existing Module', 'description' => 'Keep this']);
        $course->modules()->attach($module->module_id);
        $this->createUser(['email' => 'example-seed-admin@example.com', 'is_superadmin' => true]);

        $this->artisan('projects:seed-example', ['--course' => $course->course_id])
            ->assertSuccessful();

        $assessment = ProjectAssessment::query()
            ->where('course_id', $course->course_id)
            ->where('title', ProjectExampleSeedService::ASSESSMENT_TITLE)
            ->first();

        $this->assertNotNull($assessment);
        $this->assertTrue($assessment->is_published);
        $this->assertSame((int) $module->module_id, (int) $assessment->module_id);
        $this->assertSame(3, $assessment->projects()->count());
        $this->assertSame(3, $assessment->criteria()->count());

        $teams = $assessment->projects()->orderBy('sort_order')->get();
        $this->assertSame(['زيارة المرضى', 'خدمة المسنين', 'خدمة الأيتام'], $teams->pluck('title')->all());
        $this->assertSame('زيارة المرضى في المستشفى ودار الرعاية', $teams[0]->brief_main_title);
        $this->assertSame('رعاية المسنين في الدار والبيوت', $teams[1]->brief_main_title);
        $this->assertSame('يوم نشاط مع الأيتام وأطفال الرعية', $teams[2]->brief_main_title);

        $requirements = $teams->pluck('requirements')->all();
        $this->assertCount(3, array_unique($requirements));
        $this->assertStringContainsString('مستشفى', $requirements[0]);
        $this->assertStringContainsString('مسنين', $requirements[1]);
        $this->assertStringContainsString('أيتام', $requirements[2]);

        $this->assertSame(3, $teams[0]->phases()->count());
        $this->assertSame(3, $teams[0]->deliverables()->count());
        $this->assertSame(
            ProjectDeliverable::canonicalSlotKeys(),
            $teams[0]->deliverables()->orderBy('sort_order')->pluck('slot_key')->all()
        );
        $this->assertNotNull($assessment->submission_due_at);
        $this->assertNotSame($teams[0]->requirements, $teams[1]->requirements);
        $this->assertNotSame($teams[1]->requirements, $teams[2]->requirements);
    }

    public function test_is_idempotent_and_does_not_duplicate(): void
    {
        $course = $this->createCourse(['title' => 'Example Idempotent Course', 'status' => 'active']);
        $this->createUser(['email' => 'example-seed-idemp@example.com', 'is_superadmin' => true]);

        $this->artisan('projects:seed-example', ['--course' => $course->course_id])->assertSuccessful();
        $this->artisan('projects:seed-example', ['--course' => $course->course_id])->assertSuccessful();

        $this->assertSame(
            1,
            ProjectAssessment::query()
                ->where('course_id', $course->course_id)
                ->where('title', ProjectExampleSeedService::ASSESSMENT_TITLE)
                ->count()
        );
        $this->assertSame(3, ProjectAssessment::query()
            ->where('title', ProjectExampleSeedService::ASSESSMENT_TITLE)
            ->first()
            ->projects()
            ->count());
    }

    public function test_unpublished_flag_leaves_assessment_as_draft(): void
    {
        $course = $this->createCourse(['title' => 'Example Draft Course', 'status' => 'active']);
        $this->createUser(['email' => 'example-seed-draft@example.com', 'is_superadmin' => true]);

        $this->artisan('projects:seed-example', [
            '--course' => $course->course_id,
            '--unpublished' => true,
        ])->assertSuccessful();

        $assessment = ProjectAssessment::query()
            ->where('title', ProjectExampleSeedService::ASSESSMENT_TITLE)
            ->first();
        $this->assertNotNull($assessment);
        $this->assertFalse($assessment->is_published);
    }

    public function test_fails_when_course_is_missing(): void
    {
        $this->artisan('projects:seed-example', ['--course' => 999999])
            ->assertFailed();
    }

    public function test_refuses_production_without_force(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('projects:seed-example')
                ->assertFailed();
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_creates_example_module_when_course_has_none(): void
    {
        $course = $this->createCourse(['title' => 'Example No Module Course', 'status' => 'active']);
        $this->createUser(['email' => 'example-seed-mod@example.com', 'is_superadmin' => true]);

        $this->artisan('projects:seed-example', ['--course' => $course->course_id])
            ->assertSuccessful();

        $this->assertTrue($course->modules()->where('title', ProjectExampleSeedService::MODULE_TITLE)->exists());
    }
}
