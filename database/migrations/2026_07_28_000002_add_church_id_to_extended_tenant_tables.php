<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 — extend the tenant boundary: nullable indexed church_id on every configured
 * data-root table not yet covered by T0/T3 migrations.
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
        foreach (config('tenancy.tenant_tables', []) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'church_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('church_id'));
            }
        }
    }
};
