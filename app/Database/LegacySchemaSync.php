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
        self::syncExamsTable();
        self::ensureOtpCodeTable();
    }

    /** Run before registration so VPS legacy DB has required columns/tables. */
    public static function ensureRegistrationSchema(): void
    {
        self::syncUserTable();
        self::ensureOtpCodeTable();
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
            'national_id' => "VARCHAR(14) NOT NULL DEFAULT ''",
            'mobile_number' => 'VARCHAR(15) NOT NULL',
            'email' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'job' => "VARCHAR(50) NOT NULL DEFAULT ''",
            'date_of_birth' => 'DATE NULL',
            'password' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'is_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
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

        if (Schema::hasColumn('user', 'mobile_number')) {
            DB::statement('ALTER TABLE `user` MODIFY `mobile_number` VARCHAR(15) NOT NULL');
        }

        self::ensureLegacyNameColumnDefault();
    }

    /** Legacy VPS tables may have NOT NULL `name` without a default. */
    private static function ensureLegacyNameColumnDefault(): void
    {
        if (! Schema::hasColumn('user', 'name')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `user` MODIFY `name` VARCHAR(255) NOT NULL DEFAULT ''");
        }
    }

    private static function syncUserTableViaBlueprint(): void
    {
        MigrationSupport::addStringColumn('user', 'first_name', 30, false);
        MigrationSupport::addStringColumn('user', 'second_name', 30, false);
        MigrationSupport::addStringColumn('user', 'third_name', 30, false);
        MigrationSupport::addStringColumn('user', 'profile_photo', 255, false);
        MigrationSupport::addStringColumn('user', 'national_id', 14, false);
        MigrationSupport::addStringColumn('user', 'mobile_number', 15, false);
        MigrationSupport::addStringColumn('user', 'email', 30, false);
        MigrationSupport::addStringColumn('user', 'job', 50, false);
        MigrationSupport::addBooleanColumn('user', 'is_verified', false);
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
