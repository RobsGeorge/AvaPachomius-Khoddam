<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Services\Tenancy\StagingAcceptanceChecker;
use Tests\Support\EventModuleTestCase;

class StagingAcceptanceCheckTest extends EventModuleTestCase
{
    public function test_acceptance_check_command_passes_after_migrations(): void
    {
        $this->artisan('tenancy:acceptance-check', ['--t7' => true, '--t8' => true])
            ->assertSuccessful();
    }

    public function test_t8_checker_validates_structure_templates(): void
    {
        $checker = app(StagingAcceptanceChecker::class);
        $results = $checker->runT8();

        $this->assertFalse($checker->hasFailures($results));
        $this->assertNotNull(StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD));

        $service = ChurchService::defaultService();
        $this->assertNotNull($service);
        $this->assertSame('servants-prep', $service->slug);
    }

    public function test_t7_checker_finds_main_church(): void
    {
        $checker = app(StagingAcceptanceChecker::class);
        $results = $checker->runT7(expectMultiTenant: false);

        $names = collect($results)->pluck('name');
        $this->assertTrue($names->contains('main_church'));
        $this->assertFalse($checker->hasFailures($results));

        $this->assertNotNull(Church::main());
    }

    public function test_expect_multi_tenant_fails_when_disabled(): void
    {
        config(['tenancy.enabled' => false]);

        $this->artisan('tenancy:acceptance-check', [
            '--t7' => true,
            '--expect-multi-tenant' => true,
        ])->assertFailed();
    }
}
