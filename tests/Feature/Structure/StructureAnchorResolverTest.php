<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Services\Structure\StructureAnchorResolver;
use Tests\Support\EventModuleTestCase;

class StructureAnchorResolverTest extends EventModuleTestCase
{
    public function test_educational_standard_anchors(): void
    {
        $service = ChurchService::ensureDefault()->fresh();
        $resolver = app(StructureAnchorResolver::class);

        $this->assertSame('cohort', $resolver->enrollmentLevel($service));
        $this->assertSame('session', $resolver->attendanceLevel($service));
        $this->assertSame(['unit'], $resolver->assignmentLevels($service));
        $this->assertSame('cohort', $resolver->reportRollup($service));
        $this->assertSame(['cohort', 'unit', 'session'], $resolver->enabledLevelKeys($service));
        $this->assertSame('دفعة', $resolver->labelForLevel($service, 'cohort', 'ar'));
        $this->assertSame('Cohort', $resolver->labelForLevel($service, 'cohort', 'en'));
    }

    public function test_meeting_flat_and_care_sector_anchors(): void
    {
        $resolver = app(StructureAnchorResolver::class);

        $meeting = StructureTemplate::byKey(StructureTemplate::KEY_MEETING_FLAT);
        $care = StructureTemplate::byKey(StructureTemplate::KEY_CARE_SECTOR);
        $this->assertNotNull($meeting);
        $this->assertNotNull($care);

        $flatService = ChurchService::create([
            'title' => 'Flat Meeting Service',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'flat-meeting-test',
            'structure_template_id' => $meeting->structure_template_id,
            'permissions_version' => 0,
        ]);

        $this->assertSame('meeting', $resolver->enrollmentLevel($flatService));
        $this->assertSame('meeting', $resolver->attendanceLevel($flatService));
        $this->assertSame([], $resolver->assignmentLevels($flatService));

        $careService = ChurchService::create([
            'title' => 'Care Sector Service',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'care-sector-test',
            'structure_template_id' => $care->structure_template_id,
            'permissions_version' => 0,
        ]);

        $this->assertSame('sector', $resolver->enrollmentLevel($careService));
        $this->assertSame('household', $resolver->attendanceLevel($careService));
        $this->assertSame('sector', $resolver->reportRollup($careService));
    }

    public function test_service_enabled_levels_and_label_overrides(): void
    {
        $service = ChurchService::ensureDefault()->fresh();
        $service->update([
            'enabled_levels' => ['cohort', 'session'],
            'level_labels' => [
                'cohort' => ['label_ar' => 'دفعة مخصصة', 'label_en' => 'Custom cohort'],
            ],
        ]);

        $resolver = app(StructureAnchorResolver::class);
        $this->assertSame(['cohort', 'session'], $resolver->enabledLevelKeys($service->fresh()));
        $this->assertSame('Custom cohort', $resolver->labelForLevel($service->fresh(), 'cohort', 'en'));
        $this->assertSame('دفعة مخصصة', $resolver->labelForLevel($service->fresh(), 'cohort', 'ar'));
    }

    public function test_resolver_source_has_no_hardcoded_stage_or_class_level_names(): void
    {
        $path = app_path('Services/Structure/StructureAnchorResolver.php');
        $contents = file_get_contents($path) ?: '';

        $this->assertStringNotContainsString("'stage'", $contents);
        $this->assertStringNotContainsString('"stage"', $contents);
        $this->assertStringNotContainsString("'class'", $contents);
        $this->assertStringNotContainsString('"class"', $contents);
    }
}
