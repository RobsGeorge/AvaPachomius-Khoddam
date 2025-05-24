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
        Schema::create('user_assessment', function (Blueprint $table) {
            $table->id('user_assessment_id');
            $table->foreignId('user_id')->constrained('user', 'user_id');
            $table->foreignId('course_assessment_id')->constrained('course_assessment', 'course_assessment_id');
            $table->foreignId('submitted_by_id')->constrained('user', 'user_id');
            $table->integer('student_score');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_assessment');
    }
};
