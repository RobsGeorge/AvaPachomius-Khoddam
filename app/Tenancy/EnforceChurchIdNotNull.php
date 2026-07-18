<?php

namespace App\Tenancy;

use App\Database\MigrationSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T7 contract helpers: backfill NULL church_id → Tenant Zero, then NOT NULL (MySQL).
 *
 * Expand-phase FKs used ON DELETE SET NULL, which MySQL forbids on NOT NULL columns
 * (error 1830). Drop those FKs, flip nullability, then recreate with RESTRICT.
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
        $hasOrganizations = Schema::hasTable('organizations');

        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }
            if (in_array($table, $nullable, true)) {
                continue;
            }

            $constraint = $table.'_church_id_foreign';
            $hadForeign = $hasOrganizations && MigrationSupport::foreignKeyExists($table, $constraint);

            // ON DELETE SET NULL is incompatible with NOT NULL (MySQL error 1830).
            if ($hadForeign) {
                self::dropChurchIdForeign($table);
            }

            if (self::columnIsNullable($table, 'church_id')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `church_id` BIGINT UNSIGNED NOT NULL");
            }

            if ($hadForeign) {
                self::addChurchIdForeignRestrict($table);
            }
        }
    }

    public static function relaxNotNullOnMysql(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $nullable = (array) config('tenancy.tenant_tables_nullable_church_id', ['roles']);
        $hasOrganizations = Schema::hasTable('organizations');

        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }
            if (in_array($table, $nullable, true)) {
                continue;
            }

            $constraint = $table.'_church_id_foreign';
            $hadForeign = $hasOrganizations && MigrationSupport::foreignKeyExists($table, $constraint);

            if ($hadForeign) {
                self::dropChurchIdForeign($table);
            }

            if (! self::columnIsNullable($table, 'church_id')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `church_id` BIGINT UNSIGNED NULL");
            }

            if ($hadForeign) {
                self::addChurchIdForeignNullOnDelete($table);
            }
        }
    }

    private static function columnIsNullable(string $table, string $column): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );

        return $row !== null && strtoupper((string) $row->IS_NULLABLE) === 'YES';
    }

    private static function dropChurchIdForeign(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) {
            try {
                $blueprint->dropForeign(['church_id']);
            } catch (\Throwable) {
                // Constraint name may vary on brownfield DBs.
            }
        });
    }

    private static function addChurchIdForeignRestrict(string $table): void
    {
        $constraint = $table.'_church_id_foreign';
        if (MigrationSupport::foreignKeyExists($table, $constraint)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            try {
                $blueprint->foreign('church_id')
                    ->references('organization_id')
                    ->on('organizations')
                    ->restrictOnDelete();
            } catch (\Throwable) {
                // Orphan rows / missing organizations — leave without FK.
            }
        });
    }

    private static function addChurchIdForeignNullOnDelete(string $table): void
    {
        $constraint = $table.'_church_id_foreign';
        if (MigrationSupport::foreignKeyExists($table, $constraint)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            try {
                $blueprint->foreign('church_id')
                    ->references('organization_id')
                    ->on('organizations')
                    ->nullOnDelete();
            } catch (\Throwable) {
            }
        });
    }
}
