<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T9a expand — progression policy on templates/services + roster/enrollment status notes.
 * Additive only; does not drop UCR or rewrite structure levels.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->seedTemplateProgressionDefaults();
        $this->expandServiceColumns();
        $this->expandEnrollmentColumns();
        $this->expandUserServiceRoleColumns();
        $this->pinServantsPrepCourseCloseOnly();
    }

    public function down(): void
    {
        // Expand-only.
    }

    private function seedTemplateProgressionDefaults(): void
    {
        if (! Schema::hasTable('structure_templates')) {
            return;
        }

        $defaults = [
            'educational_standard' => ['policy' => 'school_year_ladder'],
            'meeting_flat' => ['policy' => 'continuous_open'],
            'care_sector' => ['policy' => 'continuous_open'],
        ];

        foreach ($defaults as $key => $progression) {
            $row = DB::table('structure_templates')->where('key', $key)->first();
            if (! $row) {
                continue;
            }

            $anchors = json_decode((string) $row->anchors, true);
            if (! is_array($anchors)) {
                $anchors = [];
            }
            $anchors['progression'] = $progression;

            DB::table('structure_templates')->where('key', $key)->update([
                'anchors' => json_encode($anchors, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    private function expandServiceColumns(): void
    {
        if (! Schema::hasTable('service')) {
            return;
        }

        MigrationSupport::addColumn('service', 'progression_policy', function (Blueprint $table) {
            $table->string('progression_policy', 64)->nullable()->index();
        });

        MigrationSupport::addColumn('service', 'progression_config', function (Blueprint $table) {
            $table->json('progression_config')->nullable();
        });
    }

    private function expandEnrollmentColumns(): void
    {
        if (! Schema::hasTable('enrollments')) {
            return;
        }

        MigrationSupport::addColumn('enrollments', 'status_note', function (Blueprint $table) {
            $table->string('status_note', 500)->nullable();
        });

        MigrationSupport::addColumn('enrollments', 'status_changed_at', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable();
        });
    }

    private function expandUserServiceRoleColumns(): void
    {
        if (! Schema::hasTable('user_service_role')) {
            return;
        }

        MigrationSupport::addColumn('user_service_role', 'roster_status', function (Blueprint $table) {
            $table->string('roster_status', 32)->default('active')->index();
        });

        MigrationSupport::addColumn('user_service_role', 'status_note', function (Blueprint $table) {
            $table->string('status_note', 500)->nullable();
        });

        MigrationSupport::addColumn('user_service_role', 'status_changed_at', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable();
        });
    }

    private function pinServantsPrepCourseCloseOnly(): void
    {
        if (! Schema::hasTable('service') || ! Schema::hasColumn('service', 'progression_policy')) {
            return;
        }

        DB::table('service')
            ->where('slug', 'servants-prep')
            ->whereNull('progression_policy')
            ->update([
                'progression_policy' => 'course_close_only',
                'updated_at' => now(),
            ]);
    }
};
