<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->foreignId('user_id')->constrained('user', 'user_id');
            $table->foreignId('session_id')->constrained('session', 'session_id');
            $table->foreignId('taken_by_id')->constrained('user', 'user_id');
            $table->enum('status', ['Present', 'Absent', 'Late', 'Permission']);
            $table->string('permission_reason', 50)->nullable();
            $table->timestamp('attendance_time');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
