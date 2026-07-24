<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotfix — reconcile church_id across every configured tenant table.
 *
 * The T0 / P1.1 add-column migrations (2026_07_25_000002, 2026_07_28_000002)
 * iterate config('tenancy.tenant_tables') at RUN TIME. Any table added to that
 * list *after* those migrations already executed on an environment
 * (`announcements`, the comms + service-layer tables) never received its
 * church_id column there: a fresh install has it, but a long-lived staging /
 * production database does not — schema drift.
 *
 * BelongsToChurch stamps church_id on every insert — even under
 * MULTI_TENANT=false, because ResolveTenant still binds Tenant Zero, so
 * TenantContext->churchId() returns 1 (see App\Tenancy\BelongsToChurch and
 * App\Tenancy\ResolveTenant). On the drifted schema that stamp targets a
 * non-existent column, so *any* announcement create raises "Unknown column
 * 'church_id'" → HTTP 500, and no delivery/notification rows are ever written
 * (students receive nothing).
 *
 * This migration re-adds the column on any lagging table and backfills NULL
 * rows to Tenant Zero so pre-existing rows stay visible under enforced tenancy.
 * Additive, idempotent, non-destructive (CLAUDE.md rules 1-3): a no-op on a
 * database that is already consistent. NOT NULL enforcement is intentionally
 * left to the existing T7 migration (2026_07_31_000001) to avoid an ALTER MODIFY
 * table lock during this hotfix; the column stays nullable + backfilled, which
 * is sufficient for correctness because BelongsToChurch stamps every new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('tenancy.tenant_tables', []);

        // 1. Add church_id wherever it is still missing (no-op when present).
        foreach ($tables as $table) {
            MigrationSupport::addColumn($table, 'church_id', function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('church_id')->nullable()->index();
            });
        }

        // 2. Backfill any unstamped rows to Tenant Zero so enforced-tenancy reads
        //    (MULTI_TENANT=true) do not filter out legacy data.
        if (! Schema::hasTable('church')) {
            return;
        }

        $mainId = DB::table('church')->where('slug', config('tenancy.main_slug'))->value('church_id');
        if (! $mainId) {
            return;
        }

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'church_id')) {
                DB::table($table)->whereNull('church_id')->update(['church_id' => $mainId]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: this migration only reconciles drift. Leave the
        // columns and their stamps in place (matching the T0 backfill's down()).
    }
};
