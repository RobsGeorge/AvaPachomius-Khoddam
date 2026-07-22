<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Services\ServiceContextService;
use App\Support\NavigationHub;
use Tests\Support\EventModuleTestCase;

class NavigationStructureAnchorFilterTest extends EventModuleTestCase
{
    public function test_attendance_links_hidden_when_attendance_anchor_absent(): void
    {
        $template = StructureTemplate::create([
            'key' => 'nav_filter_test_'.uniqid(),
            'name_ar' => 'اختبار',
            'name_en' => 'Nav filter test',
            'levels' => [
                ['key' => 'cohort', 'label_ar' => 'فوج', 'label_en' => 'Cohort'],
            ],
            'anchors' => [
                'enrollment_level' => 'cohort',
                // intentionally no attendance_level / assignment_levels
            ],
        ]);

        $service = ChurchService::ensureDefault();
        $service->update([
            'structure_template_id' => $template->structure_template_id,
        ]);

        $admin = $this->createUser(['is_superadmin' => true]);
        app(ServiceContextService::class)->setCurrentService($admin, $service);

        $links = NavigationHub::academicLinks($admin);
        $urls = collect($links)->pluck('url')->implode(' ');

        $this->assertStringNotContainsString(route('attendance.all', absolute: false), $urls);
        $this->assertStringNotContainsString(route('attendance.report', absolute: false), $urls);
    }

    public function test_links_kept_when_service_has_no_template(): void
    {
        $service = $this->createService([
            'title' => 'Bare Service',
            'structure_template_id' => null,
        ]);
        $admin = $this->createUser(['is_superadmin' => true]);
        app(ServiceContextService::class)->setCurrentService($admin, $service);

        // Superadmin still sees academic links; filter is a no-op without template.
        $links = NavigationHub::academicLinks($admin);
        $this->assertNotEmpty($links);
    }
}
