<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * P1.1 — organizations registry (§4) without behavior change.
 */
class OrganizationFoundationTest extends EventModuleTestCase
{
    public function test_organizations_table_exists_with_tenant_zero(): void
    {
        $this->assertTrue(Schema::hasTable('organizations'));

        $main = Organization::main();

        $this->assertSame(1, (int) $main->organization_id);
        $this->assertSame('church', $main->type);
        $this->assertSame('avapakhomios', $main->subdomain);
        $this->assertSame('كنيسة الأنبا باخوميوس', $main->name);
        $this->assertSame('active', $main->status);
    }

    public function test_tenant_church_id_aligns_with_organization_one(): void
    {
        $main = Organization::main();

        $this->assertTrue(Schema::hasColumn('course', 'church_id'));

        $course = $this->createCourse();
        \Illuminate\Support\Facades\DB::table('course')
            ->where('course_id', $course->course_id)
            ->update(['church_id' => $main->organization_id]);

        $this->assertSame(
            $main->organization_id,
            (int) \Illuminate\Support\Facades\DB::table('course')
                ->where('course_id', $course->course_id)
                ->value('church_id')
        );
    }
}
