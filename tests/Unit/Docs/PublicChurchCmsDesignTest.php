<?php

namespace Tests\Unit\Docs;

use Tests\TestCase;

/**
 * Invariants for the parked T10 Public Church Presence design.
 * Ensures the design doc and parking pointers stay complete until a T10 kickoff
 * ships product code — no CMS feature implementation in T7.
 */
class PublicChurchCmsDesignTest extends TestCase
{
    private function designDoc(): string
    {
        $path = base_path('docs/public-church-cms.md');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function parkingLot(): string
    {
        $path = base_path('PARKING-LOT.md');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function masterPlan(): string
    {
        $path = base_path('docs/khedma-master-plan.md');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_design_doc_declares_parked_t10_and_no_product_code(): void
    {
        $doc = $this->designDoc();

        $this->assertStringContainsString('T10', $doc);
        $this->assertStringContainsString('not implemented', strtolower($doc));
        $this->assertStringContainsString('Do not build', $doc);
        $this->assertStringContainsString('T8', $doc);
        $this->assertStringContainsString('What waits', $doc);
    }

    public function test_design_doc_locks_curated_homepage_not_freeform(): void
    {
        $doc = $this->designDoc();

        $this->assertStringContainsString('Homepage-only', $doc);
        $this->assertStringContainsString('Curated typed sections', $doc);
        $this->assertStringContainsString('freeform', strtolower($doc));
        $this->assertStringContainsString('Explicit non-goals', $doc);
    }

    public function test_design_doc_lists_public_site_permission_keys(): void
    {
        $doc = $this->designDoc();

        foreach ([
            'public_site.profile',
            'public_site.theme',
            'public_site.manage',
            'public_site.publish',
        ] as $key) {
            $this->assertStringContainsString($key, $doc, "Missing permission key {$key}");
        }

        $this->assertStringContainsString("'public_site'", $doc);
    }

    public function test_design_doc_lists_core_section_types_and_tables(): void
    {
        $doc = $this->designDoc();

        foreach (['hero', 'about', 'liturgy_times', 'gallery', 'cta_portal'] as $type) {
            $this->assertStringContainsString($type, $doc, "Missing section type {$type}");
        }

        foreach (['church_site', 'church_site_section', 'church_media'] as $table) {
            $this->assertStringContainsString($table, $doc, "Missing table {$table}");
        }
    }

    public function test_design_doc_covers_byo_domain_and_enterprise_tiers(): void
    {
        $doc = $this->designDoc();

        $this->assertStringContainsString('Full DNS cutover', $doc);
        $this->assertStringContainsString('Reverse proxy', $doc);
        $this->assertStringContainsString('Tier 4', $doc);
        $this->assertStringContainsString('DB-per-tenant', $doc);
        $this->assertStringContainsString('White-label', $doc);
    }

    public function test_design_doc_flags_waiting_work(): void
    {
        $doc = $this->designDoc();

        $this->assertStringContainsString('What waits', $doc);
        $this->assertStringContainsString('T10a', $doc);
        $this->assertStringContainsString('T10b', $doc);
        $this->assertStringContainsString('T10c', $doc);
        $this->assertMatchesRegularExpression('/no npm|No npm/i', $doc);
    }

    public function test_parking_lot_points_at_design_and_f20(): void
    {
        $lot = $this->parkingLot();

        $this->assertStringContainsString('Public Church Presence / Homepage CMS', $lot);
        $this->assertStringContainsString('docs/public-church-cms.md', $lot);
        $this->assertStringContainsString('T10', $lot);
        $this->assertStringContainsString('F-20', $lot);
        $this->assertStringContainsString('migrations', $lot);
        $this->assertStringContainsString('feature code waits', strtolower($lot));
        $this->assertStringContainsString('t8', strtolower($lot));
    }

    public function test_master_plan_schedules_t10_public_church_presence(): void
    {
        $plan = $this->masterPlan();

        $this->assertStringContainsString('**T10**', $plan);
        $this->assertStringContainsString('Public Church Presence', $plan);
        $this->assertStringContainsString('public-church-cms.md', $plan);
        $this->assertStringContainsString('F-20', $plan);
    }

    public function test_feature_gap_lists_f20(): void
    {
        $path = base_path('docs/product/feature-gap-analysis.md');
        $this->assertFileExists($path);
        $gap = (string) file_get_contents($path);

        $this->assertStringContainsString('F-20', $gap);
        $this->assertStringContainsString('Homepage CMS', $gap);
        $this->assertStringContainsString('T10', $gap);
    }

    public function test_no_public_site_product_code_landed_yet(): void
    {
        // Guard: T7 must not silently ship capability/permission catalog entries.
        $capabilities = (string) file_get_contents(base_path('config/capabilities.php'));
        $permissions = (string) file_get_contents(base_path('config/permissions.php'));

        $this->assertStringNotContainsString("'public_site'", $capabilities);
        $this->assertStringNotContainsString('public_site.manage', $permissions);
        $this->assertFileDoesNotExist(base_path('app/Models/ChurchSite.php'));
        $this->assertDirectoryDoesNotExist(base_path('resources/views/public-site'));
    }
}
