<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Course;
use App\Services\ChurchProvisioningService;
use App\Tenancy\EnforceChurchIdNotNull;
use App\Tenancy\ResolveTenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * T7 contract — NOT NULL church_id (via backfill + dormant stamp), pilot church,
 * and isolation when MULTI_TENANT is enabled.
 */
class TenancyCutoverTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false]);
        parent::tearDown();
    }

    public function test_dormant_creates_stamp_tenant_zero_church_id(): void
    {
        TenantContext::clear();
        $this->assertFalse(TenantContext::enforced());

        $course = Course::create([
            'title' => 'Dormant Stamp',
            'description' => 'x',
            'year' => 2026,
        ]);

        $this->assertNotNull($course->church_id);
        $this->assertEquals(Church::main()->church_id, $course->church_id);
    }

    public function test_backfill_clears_null_church_id_on_tenant_tables(): void
    {
        $mainId = Church::main()->church_id;

        $course = Course::create([
            'title' => 'Legacy Null',
            'description' => 'x',
            'year' => 2026,
        ]);

        // Force a NULL to simulate pre-contract legacy data (SQLite allows this).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $this->markTestSkipped('MySQL rejects NULL church_id after the T7 contract migration.');
        }

        DB::table('course')->where('course_id', $course->course_id)->update(['church_id' => null]);
        $this->assertNull(DB::table('course')->where('course_id', $course->course_id)->value('church_id'));

        $updated = EnforceChurchIdNotNull::backfillToMain();
        $this->assertGreaterThanOrEqual(1, $updated);

        $row = DB::table('course')->where('course_id', $course->course_id)->first();
        $this->assertNotNull($row->church_id);
        $this->assertEquals($mainId, (int) $row->church_id);
    }

    public function test_multi_tenant_resolve_binds_church_and_isolates(): void
    {
        config(['tenancy.enabled' => true]);

        $main = Church::main();
        $pilot = app(ChurchProvisioningService::class)->create([
            'slug' => 'pilot-cutover',
            'name' => 'Pilot Cutover',
            'capabilities' => (array) config('tenancy.pilot_capabilities'),
        ]);

        TenantContext::set($main);
        $courseMain = Course::create(['title' => 'Main Only', 'description' => 'x', 'year' => 2026]);

        TenantContext::set($pilot);
        $coursePilot = Course::create(['title' => 'Pilot Only', 'description' => 'x', 'year' => 2026]);

        $this->assertNull(Course::find($courseMain->course_id));
        $this->assertNotNull(Course::find($coursePilot->course_id));

        $middleware = new ResolveTenant;
        $request = Request::create('https://pilot-cutover.localhost/', 'GET');
        $request->headers->set('HOST', 'pilot-cutover.localhost');

        TenantContext::clear();
        $middleware->handle($request, function () {
            return response('ok');
        });

        $this->assertTrue(TenantContext::enforced());
        $this->assertEquals($pilot->church_id, TenantContext::id());
        $this->assertFalse($pilot->hasCapability('exams'));
        $this->assertTrue($pilot->hasCapability('church_management'));
    }

    public function test_seed_pilot_church_command_is_idempotent(): void
    {
        $this->artisan('tenancy:seed-pilot-church', [
            'slug' => 'pilot-cmd',
            '--name' => 'Pilot Cmd',
        ])->assertSuccessful();

        $this->assertTrue(Church::where('slug', 'pilot-cmd')->exists());

        $this->artisan('tenancy:seed-pilot-church', [
            'slug' => 'pilot-cmd',
            '--name' => 'Pilot Cmd',
        ])->assertSuccessful();

        $this->assertSame(1, Church::where('slug', 'pilot-cmd')->count());
    }

    public function test_roles_remain_nullable_for_platform_templates(): void
    {
        $this->assertContains('roles', config('tenancy.tenant_tables_nullable_church_id'));
        $this->assertTrue(Schema::hasColumn('roles', 'church_id'));
    }
}
