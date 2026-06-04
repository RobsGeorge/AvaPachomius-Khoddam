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

            if (Schema::hasColumn('lectures', 'session_id')
                && ! $this->foreignKeyExists('lectures', 'lectures_session_id_foreign')) {
                Schema::table('lectures', function (Blueprint $table) {
                    $table->foreign('session_id', 'lectures_session_id_foreign')
                        ->references('session_id')
                        ->on('session')
                        ->nullOnDelete();
                });
            }
        }

        $this->backfillWeekNumbers();
        $this->backfillLectureSessions();
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
            $pivots = DB::table('module_session')->whereNotNull('week_number')->get();
            foreach ($pivots as $pivot) {
                DB::table('session')
                    ->where('session_id', $pivot->session_id)
                    ->whereNull('week_number')
                    ->update(['week_number' => $pivot->week_number]);
            }
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

        $lectures = DB::table('lectures')->whereNull('session_id')->get();

        foreach ($lectures as $lecture) {
            $sessionId = DB::table('session')
                ->where('module_id', $lecture->module_id)
                ->where('week_number', $lecture->week_number)
                ->value('session_id');

            if (! $sessionId && Schema::hasTable('module_session')) {
                $sessionId = DB::table('module_session')
                    ->where('module_id', $lecture->module_id)
                    ->where('week_number', $lecture->week_number)
                    ->value('session_id');
            }

            if ($sessionId) {
                DB::table('lectures')
                    ->where('lecture_id', $lecture->lecture_id)
                    ->update(['session_id' => $sessionId]);
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
