<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Models\ServiceUnit;
use App\Models\StructureTemplate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class StructureTemplateFoundationTest extends EventModuleTestCase
{
    public function test_structure_templates_table_seeded(): void
    {
        $this->assertTrue(Schema::hasTable('structure_templates'));
        $this->assertTrue(Schema::hasColumn('service', 'slug'));
        $this->assertTrue(Schema::hasColumn('service', 'structure_template_id'));
        $this->assertTrue(Schema::hasTable('service_units'));

        foreach ([
            StructureTemplate::KEY_EDUCATIONAL_STANDARD,
            StructureTemplate::KEY_MEETING_FLAT,
            StructureTemplate::KEY_CARE_SECTOR,
        ] as $key) {
            $this->assertNotNull(StructureTemplate::byKey($key), "Missing template [{$key}]");
        }
    }

    public function test_tenant_zero_default_service_is_servants_prep(): void
    {
        $service = ChurchService::defaultService();
        $this->assertNotNull($service);
        $this->assertSame('servants-prep', $service->slug);

        $template = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $this->assertNotNull($template);
        $this->assertSame((int) $template->structure_template_id, (int) $service->structure_template_id);
    }

    public function test_courses_dual_write_to_service_units(): void
    {
        $service = ChurchService::ensureDefault();
        $this->assertSame('servants-prep', $service->fresh()->slug);

        $course = $this->createCourse([
            'title' => 'T8 Dual Write',
            'service_id' => $service->service_id,
        ]);

        $unit = ServiceUnit::query()
            ->where('course_id', $course->course_id)
            ->where('service_id', $service->service_id)
            ->first();

        $this->assertNotNull($unit);
        $this->assertSame('cohort', $unit->level_key);
        $this->assertSame('T8 Dual Write', $unit->title);
    }
}
