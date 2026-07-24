<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: widen user.email from VARCHAR(30) to VARCHAR(255).
 * RFC 5321 practical max is 254; Laravel validation already uses max:255 elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'email')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `user` MODIFY `email` VARCHAR(255) NOT NULL DEFAULT ''");
        } elseif ($driver === 'sqlite') {
            // SQLite cannot alter column length in place; fresh migrate recommended.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'email')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `user` MODIFY `email` VARCHAR(30) NOT NULL DEFAULT ''");
        }
    }
};
