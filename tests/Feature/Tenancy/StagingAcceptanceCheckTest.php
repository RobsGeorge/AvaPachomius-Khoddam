<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Services\Tenancy\StagingAcceptanceChecker;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class StagingAcceptanceCheckTest extends EventModuleTestCase
{
    public function test_acceptance_check_command_passes_after_migrations(): void
    {
        Artisan::call('permissions:sync');

        $this->artisan('tenancy:acceptance-check', ['--t7' => true, '--t8' => true, '--t10c' => true])
            ->assertSuccessful();
    }

    public function test_t10c_checker_validates_homepage_cms_schema(): void
    {
        Artisan::call('permissions:sync');

        $checker = app(StagingAcceptanceChecker::class);
        $results = $checker->runT10c();

        $this->assertFalse($checker->hasFailures($results));
        $names = collect($results)->pluck('name');
        $this->assertTrue($names->contains('t10c_table_church_site'));
        $this->assertTrue($names->contains('t10c_route_site.homepage.edit'));
    }

    public function test_t10c_only_flag_runs_without_t7_or_t8_failures(): void
    {
        Artisan::call('permissions:sync');

        $checker = app(StagingAcceptanceChecker::class);
        $this->assertFalse($checker->hasFailures($checker->runT10c()));

        $this->artisan('tenancy:acceptance-check', ['--t10c' => true])
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
