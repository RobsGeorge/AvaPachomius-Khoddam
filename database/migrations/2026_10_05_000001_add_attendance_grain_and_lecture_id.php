<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance grain: a session is one roll call, or one roll call per linked lecture.
 * Existing rows stay whole-session (lecture_id null, grain session).
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('course', 'attendance_grain', function (Blueprint $table) {
            $column = $table->string('attendance_grain', 16)->default('session');
            if (Schema::hasColumn('course', 'default_session_start_time')) {
                $column->after('default_session_start_time');
            }
        });

        MigrationSupport::addColumn('session', 'attendance_grain', function (Blueprint $table) {
            $column = $table->string('attendance_grain', 16)->default('session');
            if (Schema::hasColumn('session', 'session_start_time')) {
                $column->after('session_start_time');
            }
        });

        MigrationSupport::addColumn('attendance', 'lecture_id', function (Blueprint $table) {
            $column = $table->unsignedBigInteger('lecture_id')->nullable();
            if (Schema::hasColumn('attendance', 'session_id')) {
                $column->after('session_id');
            }
        });

        if (Schema::hasTable('attendance')
            && Schema::hasColumn('attendance', 'lecture_id')
            && ! $this->indexExists('attendance', 'attendance_lecture_id_idx')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index('lecture_id', 'attendance_lecture_id_idx');
            });
        }

        MigrationSupport::addColumn('grade_items', 'lecture_id', function (Blueprint $table) {
            $column = $table->unsignedBigInteger('lecture_id')->nullable();
            if (Schema::hasColumn('grade_items', 'session_id')) {
                $column->after('session_id');
            }
        });

        if (Schema::hasTable('grade_items')
            && Schema::hasColumn('grade_items', 'lecture_id')
            && ! $this->indexExists('grade_items', 'grade_items_lecture_id_idx')) {
            Schema::table('grade_items', function (Blueprint $table) {
                $table->index('lecture_id', 'grade_items_lecture_id_idx');
            });
        }
    }

    public function down(): void
    {
        // Expand-only.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $connection = Schema::getConnection();

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $row = $connection->selectOne(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$database, $table, $indexName]
            );

            return $row !== null;
        }

        if ($driver === 'sqlite') {
            $escaped = str_replace("'", "''", $table);
            $rows = $connection->select("PRAGMA index_list('{$escaped}')");
            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
