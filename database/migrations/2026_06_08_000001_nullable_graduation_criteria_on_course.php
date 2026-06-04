<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            if (Schema::hasColumn('course', 'passing_percentage')) {
                DB::statement('ALTER TABLE course MODIFY passing_percentage DECIMAL(5,2) NULL DEFAULT NULL');
            }
            if (Schema::hasColumn('course', 'min_attendance_percentage')) {
                DB::statement('ALTER TABLE course MODIFY min_attendance_percentage DECIMAL(5,2) NULL DEFAULT NULL');
            }
        }

        if (Schema::hasColumn('course', 'passing_percentage')
            && Schema::hasColumn('course', 'min_attendance_percentage')) {
            DB::table('course')->update([
                'passing_percentage'        => null,
                'min_attendance_percentage' => null,
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — nullable criteria is the intended schema.
    }
};
