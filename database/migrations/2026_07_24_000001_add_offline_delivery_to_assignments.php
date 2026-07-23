<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'delivery_mode')) {
                $table->string('delivery_mode', 16)->default('online')->after('resources');
            }
        });

        if (Schema::hasTable('assignment_submission')
            && Schema::hasColumn('assignment_submission', 'submission_content')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `assignment_submission` MODIFY `submission_content` TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('assignment_submission')
            && Schema::hasColumn('assignment_submission', 'submission_content')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `assignment_submission` MODIFY `submission_content` TEXT NOT NULL');
        }

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'delivery_mode')) {
                $table->dropColumn('delivery_mode');
            }
        });
    }
};
