<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_quizzes', function (Blueprint $table) {
            $table->id('live_quiz_id');
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('title', 120);
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->string('mode', 20)->default('individual');
            $table->unsignedTinyInteger('team_count')->nullable();
            $table->string('join_code', 8)->unique();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('live_quiz_questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->unsignedBigInteger('live_quiz_id')->index();
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->string('question_type', 20);
            $table->text('prompt_text')->nullable();
            $table->string('prompt_image_path')->nullable();
            $table->unsignedSmallInteger('time_limit_seconds')->default(30);
            $table->decimal('points', 8, 2)->default(1);
            $table->timestamps();

            $table->foreign('live_quiz_id')->references('live_quiz_id')->on('live_quizzes')->cascadeOnDelete();
        });

        Schema::create('live_quiz_options', function (Blueprint $table) {
            $table->id('option_id');
            $table->unsignedBigInteger('question_id')->index();
            $table->string('label_text', 500)->nullable();
            $table->string('label_image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->timestamps();

            $table->foreign('question_id')->references('question_id')->on('live_quiz_questions')->cascadeOnDelete();
        });

        Schema::create('live_quiz_sessions', function (Blueprint $table) {
            $table->id('session_id');
            $table->unsignedBigInteger('live_quiz_id')->index();
            $table->unsignedBigInteger('host_user_id')->index();
            $table->string('join_code', 8)->unique();
            $table->string('status', 20)->default('lobby');
            $table->string('mode', 20)->default('individual');
            $table->unsignedTinyInteger('team_count')->nullable();
            $table->integer('current_question_index')->nullable();
            $table->timestamp('question_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('live_quiz_id')->references('live_quiz_id')->on('live_quizzes')->cascadeOnDelete();
        });

        Schema::create('live_quiz_participants', function (Blueprint $table) {
            $table->id('participant_id');
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedTinyInteger('team_number')->nullable();
            $table->string('display_name', 120);
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->foreign('session_id')->references('session_id')->on('live_quiz_sessions')->cascadeOnDelete();
        });

        Schema::create('live_quiz_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('question_id')->index();
            $table->unsignedBigInteger('participant_id')->index();
            $table->unsignedBigInteger('option_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_earned', 8, 2)->default(0);
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['participant_id', 'question_id']);
            $table->foreign('session_id')->references('session_id')->on('live_quiz_sessions')->cascadeOnDelete();
            $table->foreign('question_id')->references('question_id')->on('live_quiz_questions')->cascadeOnDelete();
            $table->foreign('participant_id')->references('participant_id')->on('live_quiz_participants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_quiz_answers');
        Schema::dropIfExists('live_quiz_participants');
        Schema::dropIfExists('live_quiz_sessions');
        Schema::dropIfExists('live_quiz_options');
        Schema::dropIfExists('live_quiz_questions');
        Schema::dropIfExists('live_quizzes');
    }
};
