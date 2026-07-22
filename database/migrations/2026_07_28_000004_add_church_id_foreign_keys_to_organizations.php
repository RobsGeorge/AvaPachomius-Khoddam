<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 — nullable FK from tenant `church_id` → `organizations.organization_id`.
 * Expand-only: FK stays nullable until the contract phase (NOT NULL in T7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        $mainOrgId = DB::table('organizations')
            ->where('subdomain', 'avapakhomios')
            ->value('organization_id')
            ?? DB::table('organizations')->orderBy('organization_id')->value('organization_id');

        if ($mainOrgId) {
            foreach (config('tenancy.tenant_tables', []) as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'church_id')) {
                    DB::table($table)->whereNull('church_id')->update(['church_id' => $mainOrgId]);
                }
            }
        }

        foreach (config('tenancy.tenant_tables', []) as $table) {
            $constraint = $table.'_church_id_foreign';
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'church_id')
                || ! Schema::hasTable('organizations')
                || MigrationSupport::foreignKeyExists($table, $constraint)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                try {
                    $blueprint->foreign('church_id')
                        ->references('organization_id')
                        ->on('organizations')
                        ->nullOnDelete();
                } catch (\Throwable) {
                    // Brownfield: orphan rows or duplicate constraint names — skip.
                }
            });
        }

        if (Schema::hasTable('church') && Schema::hasColumn('church', 'organization_id')) {
            $constraint = 'church_organization_id_foreign';
            if (! MigrationSupport::foreignKeyExists('church', $constraint)) {
                Schema::table('church', function (Blueprint $table) {
                    try {
                        $table->foreign('organization_id')
                            ->references('organization_id')
                            ->on('organizations')
                            ->nullOnDelete();
                    } catch (\Throwable) {
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenancy.tenant_tables', []) as $table) {
            $constraint = $table.'_church_id_foreign';
            if (! Schema::hasTable($table) || ! MigrationSupport::foreignKeyExists($table, $constraint)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                try {
                    $blueprint->dropForeign(['church_id']);
                } catch (\Throwable) {
                }
            });
        }
    }
};
