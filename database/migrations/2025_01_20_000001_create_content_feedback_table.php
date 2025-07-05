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
        Schema::create('content_feedback', function (Blueprint $table) {
            $table->id('feedback_id');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('content_id')->constrained('content', 'content_id')->onDelete('cascade');
            
            // Lecture feedback
            $table->integer('lecture_rating')->nullable(); // 1-5 stars
            $table->text('lecture_comments')->nullable();
            
            // Speaker feedback
            $table->integer('speaker_rating')->nullable(); // 1-5 stars
            $table->text('speaker_comments')->nullable();
            
            // Overall feedback
            $table->text('general_feedback')->nullable();
            
            $table->timestamps();
            
            // Ensure one feedback per user per content
            $table->unique(['user_id', 'content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_feedback');
    }
}; 