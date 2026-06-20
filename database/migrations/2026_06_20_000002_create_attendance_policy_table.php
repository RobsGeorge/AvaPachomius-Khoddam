<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policy', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('late_threshold_minutes')->default(15);
            $table->decimal('late_grade_percentage', 5, 2)->default(50);
            $table->time('default_session_start_time')->default('09:00:00');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('attendance_policy')->insert([
            'id' => 1,
            'late_threshold_minutes' => (int) config('attendance.late_threshold_minutes', 15),
            'late_grade_percentage' => (float) config('attendance.late_grade_percentage', 50),
            'default_session_start_time' => config('attendance.default_session_start_time', '09:00:00'),
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policy');
    }
};
