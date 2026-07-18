<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T0 — add a nullable, indexed `church_id` to each data-root tenant table.
 * Nullable + no hard FK on purpose: the backfill (next migration) stamps every
 * row, then T1 flips to NOT NULL (+ optional FK) once verified. Idempotent:
 * MigrationSupport::addColumn no-ops when the table is missing or the column
 * already exists, so the column and its index are only ever created together.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenancy.tenant_tables') as $table) {
            MigrationSupport::addColumn($table, 'church_id', function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('church_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenancy.tenant_tables') as $table) {
            if (Schema::hasColumn($table, 'church_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('church_id'));
            }
        }
    }
};
