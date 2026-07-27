<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Tenancy\RequireCapability;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EventModuleTestCase;

/**
 * T2 — per-church capabilities (docs/khedma-master-plan.md §8). The main church has every
 * catalog capability (status quo preserved); a fresh church has none; the middleware 404s a
 * disabled feature but is dormant when no church is bound (production until the T7 cutover).
 */
class ChurchCapabilityTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_main_church_has_all_catalog_capabilities(): void
    {
        $main = Church::main();

        foreach (array_keys(config('capabilities')) as $key) {
            $this->assertTrue($main->hasCapability($key), "main church should have capability: {$key}");
        }
    }

    public function test_a_fresh_church_has_no_capabilities(): void
    {
        $church = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        $this->assertFalse($church->hasCapability('exams'));
        $this->assertFalse($church->hasCapability('attendance'));
    }

    public function test_unknown_capability_key_is_fail_open(): void
    {
        $church = Church::create(['slug' => 'stgeorge', 'name' => 'St George', 'status' => 'active']);

        $this->assertTrue($church->hasCapability('feature_not_in_catalog'));
    }

    public function test_capability_config_merges_catalog_defaults_with_overrides(): void
    {
        $this->assertSame(75, Church::main()->capabilityConfig('attendance')['min_percentage']);

        $church = Church::create(['slug' => 'lenient', 'name' => 'Lenient', 'status' => 'active']);
        ChurchCapability::create([
            'church_id' => $church->church_id,
            'capability_key' => 'attendance',
            'enabled' => true,
            'config' => ['min_percentage' => 0, 'mode' => 'lenient'],
        ]);

        $cfg = $church->fresh()->capabilityConfig('attendance');
        $this->assertSame(0, $cfg['min_percentage']);        // override wins
        $this->assertSame('lenient', $cfg['mode']);          // override wins
        $this->assertTrue($cfg['penalty']);                  // catalog default preserved
    }

    public function test_require_capability_middleware_enforces_only_when_a_church_is_bound(): void
    {
        $middleware = new RequireCapability();
        $main = Church::main();
        $noExams = Church::create(['slug' => 'noexams', 'name' => 'No Exams', 'status' => 'active']);

        // Bound church that has the capability → passes.
        TenantContext::set($main);
        $this->assertSame('ok', $middleware->handle(Request::create('/'), fn () => 'ok', 'exams'));

        // Bound church without the capability → 404 (feature does not exist there).
        TenantContext::set($noExams);
        try {
            $middleware->handle(Request::create('/'), fn () => 'ok', 'exams');
            $this->fail('Expected a 404 for a disabled capability.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        // No church bound (tenancy dormant) → passes, so production is unaffected.
        TenantContext::clear();
        $this->assertSame('ok', $middleware->handle(Request::create('/'), fn () => 'ok', 'exams'));
    }
}
