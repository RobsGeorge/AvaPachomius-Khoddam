<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T7 contract helpers: backfill NULL church_id → Tenant Zero, then NOT NULL (MySQL).
 */
final class EnforceChurchIdNotNull
{
    public static function backfillToMain(): int
    {
        if (! Schema::hasTable('church')) {
            return 0;
        }

        $mainId = DB::table('church')
            ->where('slug', config('tenancy.main_slug'))
            ->value('church_id');

        if (! $mainId) {
            return 0;
        }

        $updated = 0;
        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }
            $updated += DB::table($table)->whereNull('church_id')->update(['church_id' => $mainId]);
        }

        return $updated;
    }

    public static function enforceNotNullOnMysql(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $nullable = (array) config('tenancy.tenant_tables_nullable_church_id', ['roles']);

        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }
            if (in_array($table, $nullable, true)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `church_id` BIGINT UNSIGNED NOT NULL");
        }
    }

    public static function relaxNotNullOnMysql(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $nullable = (array) config('tenancy.tenant_tables_nullable_church_id', ['roles']);

        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }
            if (in_array($table, $nullable, true)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `church_id` BIGINT UNSIGNED NULL");
        }
    }
}
