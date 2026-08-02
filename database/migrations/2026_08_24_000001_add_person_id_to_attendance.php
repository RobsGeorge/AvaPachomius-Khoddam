<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR attendance expand — person_id alongside user_id (rung-0 can be marked present).
 * user_id is loosened to nullable (expand); never dropped/renamed (Phase-5 contraction).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance')) {
            return;
        }

        // No ->index() here: SQLite index names are DB-global and collide on table rebuild.
        MigrationSupport::addColumn('attendance', 'person_id', function (Blueprint $table) {
            $after = Schema::hasColumn('attendance', 'user_id') ? 'user_id' : null;
            $col = $table->unsignedBigInteger('person_id')->nullable();
            if ($after !== null) {
                $col->after($after);
            }
        });

        $this->makeUserIdNullable();
        $this->ensureIndexes();

        if (Schema::hasTable('people')
            && Schema::hasColumn('attendance', 'person_id')
            && ! MigrationSupport::foreignKeyExists('attendance', 'attendance_person_id_foreign')) {
            try {
                Schema::table('attendance', function (Blueprint $table) {
                    $table->foreign('person_id')
                        ->references('person_id')
                        ->on('people')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                // SQLite / partial envs may skip FKs; column + index remain.
            }
        }
    }

    public function down(): void
    {
        // Expand-only — no contraction.
    }

    private function ensureIndexes(): void
    {
        if (Schema::hasColumn('attendance', 'person_id')
            && ! $this->indexExists('attendance', 'attendance_person_id_idx')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index('person_id', 'attendance_person_id_idx');
            });
        }

        if (Schema::hasColumn('attendance', 'person_id')
            && Schema::hasColumn('attendance', 'session_id')
            && ! $this->indexExists('attendance', 'attendance_session_person_idx')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index(['session_id', 'person_id'], 'attendance_session_person_idx');
            });
        }
    }

    private function makeUserIdNullable(): void
    {
        if (! Schema::hasColumn('attendance', 'user_id') || $this->userIdIsNullable()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (MigrationSupport::foreignKeyExists('attendance', 'attendance_user_id_foreign')) {
                Schema::table('attendance', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            }

            DB::statement('ALTER TABLE `attendance` MODIFY `user_id` BIGINT UNSIGNED NULL');

            if (! MigrationSupport::foreignKeyExists('attendance', 'attendance_user_id_foreign')) {
                Schema::table('attendance', function (Blueprint $table) {
                    $table->foreign('user_id')->references('user_id')->on('user')->nullOnDelete();
                });
            }

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildAttendanceSqliteWithNullableUserId();
        }
    }

    private function userIdIsNullable(): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $connection = Schema::getConnection();

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $row = $connection->selectOne(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                [$database, 'attendance', 'user_id']
            );
            $nullable = is_object($row) ? ($row->IS_NULLABLE ?? null) : ($row['IS_NULLABLE'] ?? null);

            return strtoupper((string) $nullable) === 'YES';
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA table_info('attendance')");
            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === 'user_id') {
                    $notnull = is_object($row) ? (int) ($row->notnull ?? 1) : (int) ($row['notnull'] ?? 1);

                    return $notnull === 0;
                }
            }
        }

        return false;
    }

    /**
     * SQLite cannot MODIFY nullability — rebuild (tests only; MySQL uses ALTER).
     */
    private function rebuildAttendanceSqliteWithNullableUserId(): void
    {
        $columns = Schema::getColumnListing('attendance');
        if ($columns === []) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::rename('attendance', 'attendance__person_id_tmp');

        Schema::create('attendance', function (Blueprint $table) use ($columns) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('taken_by_id');
            $table->string('status', 32);
            $table->string('permission_reason', 50)->nullable();
            $table->timestamp('attendance_time');
            $table->timestamps();
            if (in_array('church_id', $columns, true)) {
                $table->unsignedBigInteger('church_id')->nullable();
            }
            if (in_array('lock_version', $columns, true)) {
                $table->unsignedInteger('lock_version')->default(0);
            }
        });

        $selectCols = [
            'attendance_id',
            'user_id',
            'person_id',
            'session_id',
            'taken_by_id',
            'status',
            'permission_reason',
            'attendance_time',
            'created_at',
            'updated_at',
        ];
        if (in_array('church_id', $columns, true)) {
            $selectCols[] = 'church_id';
        }
        if (in_array('lock_version', $columns, true)) {
            $selectCols[] = 'lock_version';
        }

        $tmpCols = Schema::getColumnListing('attendance__person_id_tmp');
        $available = array_values(array_intersect($selectCols, $tmpCols));
        $colList = implode(', ', $available);

        DB::statement("INSERT INTO attendance ({$colList}) SELECT {$colList} FROM attendance__person_id_tmp");

        Schema::drop('attendance__person_id_tmp');
        Schema::enableForeignKeyConstraints();
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
