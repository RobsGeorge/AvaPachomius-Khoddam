<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LegacySchemaSync
{
    /**
     * Bring brownfield tables in line with what the app and migrations expect.
     * Safe to run on every deploy; only adds missing columns/PK names.
     */
    public static function syncAll(): void
    {
        LegacyPrimaryKeys::normalizeAll();
        self::syncUserTable();
        self::syncSessionTable();
        self::syncLecturesTable();
        self::syncCourseModuleTable();
        self::syncExamsTable();
        self::syncPortalSettingsTable();
        self::ensureOtpCodeTable();
    }

    /** Run before registration so VPS legacy DB has required columns/tables. */
    public static function ensureRegistrationSchema(): void
    {
        self::syncUserTable();
        self::ensureOtpCodeTable();
    }

    /** Ensure portal/display columns exist before layout rendering (brownfield-safe). */
    public static function ensureDisplayPreferencesSchema(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            self::addMysqlColumnIfMissing('user', 'font_size_preference', "VARCHAR(20) NOT NULL DEFAULT 'normal'");
        } else {
            MigrationSupport::addColumn('user', 'font_size_preference', function (Blueprint $table) {
                $definition = $table->string('font_size_preference', 20)->default('normal');

                if (Schema::hasColumn('user', 'student_onboarding_completed_at')) {
                    $definition->after('student_onboarding_completed_at');
                }
            });
        }

        self::syncPortalSettingsTable();
    }

    private static function syncSessionTable(): void
    {
        if (! Schema::hasTable('session')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $columns = [
                'course_id' => 'BIGINT UNSIGNED NOT NULL',
                'module_id' => 'BIGINT UNSIGNED NULL',
                'week_number' => 'SMALLINT UNSIGNED NULL',
                'session_title' => "VARCHAR(30) NOT NULL DEFAULT ''",
                'session_date' => 'DATE NOT NULL',
                'created_at' => 'TIMESTAMP NULL',
                'updated_at' => 'TIMESTAMP NULL',
            ];

            foreach ($columns as $column => $definition) {
                self::addMysqlColumnIfMissing('session', $column, $definition);
            }

            return;
        }

        MigrationSupport::addStringColumn('session', 'session_title', 30, false);
        MigrationSupport::addDateColumn('session', 'session_date', false);
    }

    private static function syncLecturesTable(): void
    {
        if (! Schema::hasTable('lectures') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        self::addMysqlColumnIfMissing('lectures', 'session_id', 'BIGINT UNSIGNED NULL');
    }

    private static function syncCourseModuleTable(): void
    {
        if (! Schema::hasTable('course_module') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'start_date' => 'DATE NULL',
            'end_date' => 'DATE NULL',
            'order_index' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'draft'",
            'feedback_open' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'ended_at' => 'TIMESTAMP NULL',
            'ended_by_user_id' => 'BIGINT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addMysqlColumnIfMissing('course_module', $column, $definition);
        }
    }

    private static function syncExamsTable(): void
    {
        if (! Schema::hasTable('exams') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'course_id' => 'BIGINT UNSIGNED NULL',
            'module_id' => 'BIGINT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addMysqlColumnIfMissing('exams', $column, $definition);
        }
    }

    private static function syncPortalSettingsTable(): void
    {
        if (! Schema::hasTable('portal_settings')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            MigrationSupport::addColumn('portal_settings', 'theme_colors_draft', function (Blueprint $table) {
                $table->json('theme_colors_draft')->nullable();
            });
            MigrationSupport::addColumn('portal_settings', 'theme_colors_published', function (Blueprint $table) {
                $table->json('theme_colors_published')->nullable();
            });
            MigrationSupport::addColumn('portal_settings', 'theme_colors_published_at', function (Blueprint $table) {
                $table->timestamp('theme_colors_published_at')->nullable();
            });
            MigrationSupport::addColumn('portal_settings', 'theme_colors_published_by_user_id', function (Blueprint $table) {
                $table->unsignedBigInteger('theme_colors_published_by_user_id')->nullable();
            });

            return;
        }

        foreach ([
            'profile_photo_gate_enabled_at' => 'TIMESTAMP NULL',
            'theme_colors_draft' => 'JSON NULL',
            'theme_colors_published' => 'JSON NULL',
            'theme_colors_published_at' => 'TIMESTAMP NULL',
            'theme_colors_published_by_user_id' => 'BIGINT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addMysqlColumnIfMissing('portal_settings', $column, $definition);
        }
    }

    private static function ensureOtpCodeTable(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        SchemaGuards::createTableIfMissing('otp_code', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->primary('user_id');
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->string('code', 10);
            $table->timestamp('expires_at');
        });
    }

    private static function syncUserTable(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            self::syncUserTableViaBlueprint();

            return;
        }

        $columns = [
            'first_name' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'second_name' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'third_name' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'profile_photo' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'profile_photo_grace_started_at' => 'TIMESTAMP NULL',
            'profile_photo_deadline_at' => 'TIMESTAMP NULL',
            'profile_photo_uploaded_at' => 'TIMESTAMP NULL',
            'profile_photo_status' => 'VARCHAR(20) NULL',
            'profile_photo_reviewed_at' => 'TIMESTAMP NULL',
            'profile_photo_reviewed_by_user_id' => 'BIGINT UNSIGNED NULL',
            'profile_photo_rejection_note' => 'TEXT NULL',
            'student_onboarding_completed_at' => 'TIMESTAMP NULL',
            'font_size_preference' => "VARCHAR(20) NOT NULL DEFAULT 'normal'",
            'national_id' => "VARCHAR(14) NOT NULL DEFAULT ''",
            'mobile_number' => 'VARCHAR(15) NOT NULL',
            'email' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'job' => "VARCHAR(50) NOT NULL DEFAULT ''",
            'date_of_birth' => 'DATE NULL',
            'password' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'is_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'registration_completed' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'is_superadmin' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'remember_token' => 'VARCHAR(100) NULL',
            'otp_code' => 'VARCHAR(255) NULL',
            'otp_expires_at' => 'TIMESTAMP NULL',
            'created_at' => 'TIMESTAMP NULL',
            'updated_at' => 'TIMESTAMP NULL',
        ];

        foreach ($columns as $column => $definition) {
            self::addMysqlColumnIfMissing('user', $column, $definition);
        }

        // Column width is handled by migration 2026_06_01_000006 — do not MODIFY on every deploy.
        self::ensureLegacyNameColumnDefault();
    }

    /** Legacy VPS tables may have NOT NULL `name` without a default. One-time fix only. */
    private static function ensureLegacyNameColumnDefault(): void
    {
        if (! Schema::hasColumn('user', 'name')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $row = DB::selectOne(
            "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'name'"
        );

        if ($row !== null && $row->COLUMN_DEFAULT !== null) {
            return;
        }

        DB::statement("ALTER TABLE `user` MODIFY `name` VARCHAR(255) NOT NULL DEFAULT ''");
    }

    private static function syncUserTableViaBlueprint(): void
    {
        MigrationSupport::addStringColumn('user', 'first_name', 30, false);
        MigrationSupport::addStringColumn('user', 'second_name', 30, false);
        MigrationSupport::addStringColumn('user', 'third_name', 30, false);
        MigrationSupport::addStringColumn('user', 'profile_photo', 255, false);
        MigrationSupport::addColumn('user', 'profile_photo_grace_started_at', function ($table) {
            $table->timestamp('profile_photo_grace_started_at')->nullable()->after('profile_photo');
        });
        MigrationSupport::addColumn('user', 'profile_photo_deadline_at', function ($table) {
            $table->timestamp('profile_photo_deadline_at')->nullable()->after('profile_photo_grace_started_at');
        });
        MigrationSupport::addColumn('user', 'profile_photo_uploaded_at', function ($table) {
            $table->timestamp('profile_photo_uploaded_at')->nullable()->after('profile_photo_deadline_at');
        });
        MigrationSupport::addStringColumn('user', 'profile_photo_status', 20, true, 'profile_photo_uploaded_at');
        MigrationSupport::addColumn('user', 'profile_photo_reviewed_at', function ($table) {
            $table->timestamp('profile_photo_reviewed_at')->nullable()->after('profile_photo_status');
        });
        MigrationSupport::addColumn('user', 'profile_photo_reviewed_by_user_id', function ($table) {
            $table->unsignedBigInteger('profile_photo_reviewed_by_user_id')->nullable()->after('profile_photo_reviewed_at');
        });
        MigrationSupport::addTextColumn('user', 'profile_photo_rejection_note', true, 'profile_photo_reviewed_by_user_id');
        MigrationSupport::addColumn('user', 'student_onboarding_completed_at', function ($table) {
            $table->timestamp('student_onboarding_completed_at')->nullable()->after('profile_photo_rejection_note');
        });
        MigrationSupport::addColumn('user', 'font_size_preference', function (Blueprint $table) {
            $definition = $table->string('font_size_preference', 20)->default('normal');

            if (Schema::hasColumn('user', 'student_onboarding_completed_at')) {
                $definition->after('student_onboarding_completed_at');
            }
        });
        MigrationSupport::addStringColumn('user', 'national_id', 14, false);
        MigrationSupport::addStringColumn('user', 'mobile_number', 15, false);
        MigrationSupport::addStringColumn('user', 'email', 30, false);
        MigrationSupport::addStringColumn('user', 'job', 50, false);
        MigrationSupport::addBooleanColumn('user', 'is_verified', false);
        MigrationSupport::addBooleanColumn('user', 'registration_completed', false, 'is_verified');
        MigrationSupport::addBooleanColumn('user', 'is_superadmin', false, 'is_verified');
        MigrationSupport::addStringColumn('user', 'remember_token', 100);
        MigrationSupport::addStringColumn('user', 'otp_code', 255);
    }

    private static function addMysqlColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        $quotedTable = '`'.str_replace('`', '``', $table).'`';
        $quotedColumn = '`'.str_replace('`', '``', $column).'`';

        DB::statement("ALTER TABLE {$quotedTable} ADD COLUMN {$quotedColumn} {$definition}");
    }
}
