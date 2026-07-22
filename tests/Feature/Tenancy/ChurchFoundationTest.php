<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * T0 — church tenancy foundation (docs/khedma-master-plan.md §7). Verifies the
 * tenant + membership tables, the default (Tenant Zero) church, the church_id
 * boundary column on every configured tenant table, and shared-pool membership.
 * T0 has no global scope, so this only checks structure + membership plumbing.
 */
class ChurchFoundationTest extends EventModuleTestCase
{
    public function test_church_and_membership_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('church'));
        $this->assertTrue(Schema::hasTable('church_user'));
    }

    public function test_default_main_church_is_seeded(): void
    {
        $main = Church::main();

        $this->assertSame(config('tenancy.main_slug'), $main->slug);
        $this->assertSame('active', $main->status);
    }

    public function test_every_configured_tenant_table_has_a_church_id_column(): void
    {
        $missing = [];
        foreach (config('tenancy.tenant_tables') as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'church_id')) {
                $missing[] = $table;
            }
        }

        $this->assertSame([], $missing, 'Tenant tables missing church_id: '.implode(', ', $missing));
    }

    public function test_tenant_rows_can_be_stamped_with_a_church(): void
    {
        $main = Church::main();
        $course = $this->createCourse();

        DB::table('course')->where('course_id', $course->course_id)->update(['church_id' => $main->church_id]);

        $this->assertEquals(
            $main->church_id,
            DB::table('course')->where('course_id', $course->course_id)->value('church_id')
        );
    }

    public function test_membership_governs_belongs_to_church(): void
    {
        $main = Church::main();
        $other = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        $user = $this->createUser(['email' => 'church-member@example.com']);

        ChurchUser::create([
            'church_id' => $main->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertTrue($user->belongsToChurch($main->church_id));
        $this->assertFalse($user->belongsToChurch($other->church_id));
        $this->assertSame(1, $user->churches()->count());
    }

    public function test_membership_is_unique_per_user_and_church(): void
    {
        $main = Church::main();
        $user = $this->createUser(['email' => 'church-unique@example.com']);

        $row = ['church_id' => $main->church_id, 'user_id' => $user->user_id, 'status' => 'active', 'joined_at' => now()];
        DB::table('church_user')->insertOrIgnore($row);
        DB::table('church_user')->insertOrIgnore($row); // re-run must not duplicate

        $this->assertSame(1, DB::table('church_user')
            ->where('church_id', $main->church_id)->where('user_id', $user->user_id)->count());
    }
}
