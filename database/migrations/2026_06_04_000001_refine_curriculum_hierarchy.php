<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('session')) {
            MigrationSupport::addColumn('session', 'week_number', function (Blueprint $table) {
                $table->unsignedSmallInteger('week_number')->nullable()->after('module_id');
            });
        }

        if (Schema::hasTable('lectures') && Schema::hasTable('session')) {
            MigrationSupport::addColumn('lectures', 'session_id', function (Blueprint $table) {
                $table->unsignedBigInteger('session_id')->nullable()->after('module_id');
            });
        }

        $this->backfillWeekNumbers();
        $this->backfillLectureSessions();
        $this->addLectureSessionForeignKey();
    }

    public function down(): void
    {
        if (Schema::hasTable('lectures') && $this->foreignKeyExists('lectures', 'lectures_session_id_foreign')) {
            Schema::table('lectures', function (Blueprint $table) {
                $table->dropForeign('lectures_session_id_foreign');
            });
        }

        if (Schema::hasTable('lectures') && Schema::hasColumn('lectures', 'session_id')) {
            Schema::table('lectures', function (Blueprint $table) {
                $table->dropColumn('session_id');
            });
        }

        if (Schema::hasTable('session') && Schema::hasColumn('session', 'week_number')) {
            Schema::table('session', function (Blueprint $table) {
                $table->dropColumn('week_number');
            });
        }
    }

    private function backfillWeekNumbers(): void
    {
        if (! Schema::hasTable('session') || ! Schema::hasColumn('session', 'week_number')) {
            return;
        }

        if (Schema::hasTable('module_session')) {
            DB::statement('
                UPDATE `session` s
                INNER JOIN `module_session` ms ON ms.session_id = s.session_id
                SET s.week_number = ms.week_number
                WHERE s.week_number IS NULL AND ms.week_number IS NOT NULL
            ');
        }

        $modules = DB::table('session')
            ->whereNotNull('module_id')
            ->whereNull('week_number')
            ->orderBy('module_id')
            ->orderBy('session_date')
            ->get(['session_id', 'module_id']);

        $weekByModule = [];
        foreach ($modules as $row) {
            $weekByModule[$row->module_id] = ($weekByModule[$row->module_id] ?? 0) + 1;
            DB::table('session')
                ->where('session_id', $row->session_id)
                ->update(['week_number' => $weekByModule[$row->module_id]]);
        }
    }

    private function backfillLectureSessions(): void
    {
        if (! Schema::hasTable('lectures') || ! Schema::hasColumn('lectures', 'session_id')) {
            return;
        }

        DB::statement('
            UPDATE `lectures` l
            INNER JOIN `session` s
                ON s.module_id = l.module_id AND s.week_number = l.week_number
            SET l.session_id = s.session_id
            WHERE l.session_id IS NULL
        ');

        if (! Schema::hasTable('module_session')) {
            return;
        }

        DB::statement('
            UPDATE `lectures` l
            INNER JOIN `module_session` ms
                ON ms.module_id = l.module_id AND ms.week_number = l.week_number
            INNER JOIN `session` s ON s.session_id = ms.session_id
            SET l.session_id = s.session_id
            WHERE l.session_id IS NULL
        ');
    }

    private function addLectureSessionForeignKey(): void
    {
        if (! Schema::hasTable('lectures')
            || ! Schema::hasColumn('lectures', 'session_id')
            || $this->foreignKeyExists('lectures', 'lectures_session_id_foreign')) {
            return;
        }

        // Avoid hanging deploys on metadata locks; skip FK if table is busy.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('SET SESSION lock_wait_timeout = 120');
        }

        try {
            Schema::table('lectures', function (Blueprint $table) {
                $table->foreign('session_id', 'lectures_session_id_foreign')
                    ->references('session_id')
                    ->on('session')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                report($e);
            } else {
                throw $e;
            }
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return $row !== null;
    }
};
