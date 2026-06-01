<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `user` MODIFY `mobile_number` VARCHAR(15) NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot alter column length in place; fresh migrate recommended.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `user` MODIFY `mobile_number` VARCHAR(10) NOT NULL');
        }
    }
};
