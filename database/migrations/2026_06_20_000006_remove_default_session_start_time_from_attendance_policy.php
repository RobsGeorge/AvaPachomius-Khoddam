<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policy', function (Blueprint $table) {
            $table->dropColumn('default_session_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policy', function (Blueprint $table) {
            $table->time('default_session_start_time')->default('09:00:00')->after('late_grade_percentage');
        });
    }
};
