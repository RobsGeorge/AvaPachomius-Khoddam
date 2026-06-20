<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->timestamp('attendance_closed_at')->nullable()->after('session_date');
            $table->foreignId('attendance_closed_by_id')
                ->nullable()
                ->after('attendance_closed_at')
                ->constrained('user', 'user_id');
        });
    }

    public function down(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->dropForeign(['attendance_closed_by_id']);
            $table->dropColumn(['attendance_closed_at', 'attendance_closed_by_id']);
        });
    }
};
