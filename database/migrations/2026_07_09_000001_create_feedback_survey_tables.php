<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_surveys', function (Blueprint $table) {
            $table->id('survey_id');
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('module_id')->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'module_id', 'status']);
        });

        Schema::create('feedback_questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->unsignedBigInteger('survey_id')->index();
            $table->string('question_type', 20);
            $table->string('scope', 20)->default('general');
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('lecture_id')->nullable()->index();
            $table->unsignedBigInteger('target_user_id')->nullable()->index();
            $table->string('label', 500);
            $table->text('help_text')->nullable();
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->boolean('is_required')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('feedback_surveys')->cascadeOnDelete();
        });

        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id('submission_id');
            $table->unsignedBigInteger('survey_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['survey_id', 'user_id']);
            $table->foreign('survey_id')->references('survey_id')->on('feedback_surveys')->cascadeOnDelete();
        });

        Schema::create('feedback_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('question_id')->index();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
            $table->foreign('submission_id')->references('submission_id')->on('feedback_submissions')->cascadeOnDelete();
            $table->foreign('question_id')->references('question_id')->on('feedback_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_answers');
        Schema::dropIfExists('feedback_submissions');
        Schema::dropIfExists('feedback_questions');
        Schema::dropIfExists('feedback_surveys');
    }
};
