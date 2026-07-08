<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_feedback_sessions', function (Blueprint $table) {
            $table->id('session_id');
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('module_id')->index();
            $table->unsignedBigInteger('host_user_id')->index();
            $table->string('status', 20)->default('live');
            $table->boolean('mandatory_gate')->default(true);
            $table->boolean('live_mode')->default(true);
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'module_id', 'status']);
        });

        Schema::create('live_feedback_responses', function (Blueprint $table) {
            $table->id('response_id');
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedTinyInteger('lecture_rating')->nullable();
            $table->text('lecture_comments')->nullable();
            $table->unsignedTinyInteger('speaker_rating')->nullable();
            $table->text('speaker_comments')->nullable();
            $table->unsignedTinyInteger('workshop_rating')->nullable();
            $table->text('workshop_comments')->nullable();
            $table->unsignedTinyInteger('timing_rating')->nullable();
            $table->text('timing_comments')->nullable();
            $table->unsignedTinyInteger('content_rating')->nullable();
            $table->text('content_comments')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->foreign('session_id')->references('session_id')->on('live_feedback_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_feedback_responses');
        Schema::dropIfExists('live_feedback_sessions');
    }
};
