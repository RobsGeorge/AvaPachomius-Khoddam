<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'email')) {
            return;
        }

        if ($this->indexExists('user', 'user_email_unique')) {
            return;
        }

        $duplicateEmails = DB::table('user')
            ->select('email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        if ($duplicateEmails->isNotEmpty()) {
            return;
        }

        Schema::table('user', function (Blueprint $table) {
            $table->unique('email', 'user_email_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        if ($this->indexExists('user', 'user_email_unique')) {
            Schema::table('user', function (Blueprint $table) {
                $table->dropUnique('user_email_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

            return count($rows) > 0;
        }

        return false;
    }
};
