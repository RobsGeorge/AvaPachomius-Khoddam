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
        Schema::create('course_assessment', function (Blueprint $table) {
            $table->id('course_assessment_id');
            $table->foreignId('course_id')->constrained('course', 'course_id');
            $table->foreignId('assessment_id')->constrained('assessment', 'assessment_id');
            $table->integer('max_score');
            $table->integer('min_score');
            $table->date('assessment_date');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_assessment');
    }
};
