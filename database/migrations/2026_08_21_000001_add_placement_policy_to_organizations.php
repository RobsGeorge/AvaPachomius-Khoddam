<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diocese-tier residency seam (ADR slice 1, regrounded) — placement policy on the
 * existing organizations registry. Defaults keep everyone on the shared DB;
 * Tenant Zero is unchanged beyond additive column defaults.
 *
 * placement_policy is a string (shared|diocese_db|church_db), not MySQL enum —
 * matches this app's status-column style and SQLite test parity.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('organizations', 'placement_policy', function (Blueprint $table) {
            $table->string('placement_policy', 32)->default('shared')->after('status');
        });
        MigrationSupport::addBooleanColumn('organizations', 'db_isolated', false, 'placement_policy');
        MigrationSupport::addStringColumn('organizations', 'db_name', 191, true, 'db_isolated');
        MigrationSupport::addStringColumn('organizations', 'db_user', 191, true, 'db_name');
        MigrationSupport::addTextColumn('organizations', 'db_password_encrypted', true, 'db_user');
    }

    public function down(): void
    {
        foreach (['db_password_encrypted', 'db_user', 'db_name', 'db_isolated', 'placement_policy'] as $column) {
            if (Schema::hasColumn('organizations', $column)) {
                Schema::table('organizations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
