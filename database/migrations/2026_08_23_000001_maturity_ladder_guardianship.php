<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maturity ladder + guardianship (ADR Slice 6, regrounded).
 * Extends existing relationships edges; adds consent_log + age_policies.
 * Never hardcodes majority age in application code — thresholds live here / config.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addDateColumn('relationships', 'start_date', true, 'type');
        MigrationSupport::addDateColumn('relationships', 'end_date', true, 'start_date');
        MigrationSupport::addColumn('relationships', 'verified_by', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_by')->nullable()->index()->after('end_date');
        });
        MigrationSupport::addColumn('relationships', 'guardian_visibility', function (Blueprint $table) {
            $after = Schema::hasColumn('relationships', 'verified_by') ? 'verified_by' : 'type';
            $table->string('guardian_visibility', 32)->default('full')->after($after);
        });

        // Existing edges: interpret start as created_at date (documented default).
        if (Schema::hasTable('relationships') && Schema::hasColumn('relationships', 'start_date')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('UPDATE relationships SET start_date = date(created_at) WHERE start_date IS NULL AND created_at IS NOT NULL');
            } else {
                DB::statement('UPDATE relationships SET start_date = DATE(created_at) WHERE start_date IS NULL AND created_at IS NOT NULL');
            }
        }

        MigrationSupport::addBooleanColumn('people', 'is_minor', false, 'gender');

        // Idempotent mirror for recovery-ladder gate (Slice 5); safe if that PR lands first.
        MigrationSupport::addBooleanColumn('user', 'is_minor', false, 'whatsapp_capable');

        SchemaGuards::createTableIfMissing('consent_log', function (Blueprint $table) {
            $table->id('consent_log_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->unsignedBigInteger('consented_by')->index();
            $table->string('scope', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['person_id', 'scope', 'created_at']);
        });

        SchemaGuards::createTableIfMissing('age_policies', function (Blueprint $table) {
            $table->id('age_policy_id');
            $table->unsignedBigInteger('organization_id')->unique();
            // Defaults come from config/maturity.php — do not treat as hardcoded thresholds in services.
            $table->unsignedTinyInteger('digital_consent_age');
            $table->unsignedTinyInteger('age_of_majority');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('age_policies');
        Schema::dropIfExists('consent_log');

        foreach (['guardian_visibility', 'verified_by', 'end_date', 'start_date'] as $column) {
            if (Schema::hasColumn('relationships', $column)) {
                Schema::table('relationships', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('people', 'is_minor')) {
            Schema::table('people', function (Blueprint $table) {
                $table->dropColumn('is_minor');
            });
        }
        // Leave user.is_minor — shared with Slice 5; Phase-5 contraction only.
    }
};
