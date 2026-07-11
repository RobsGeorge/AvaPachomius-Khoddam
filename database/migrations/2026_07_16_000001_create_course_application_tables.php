<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_application_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->string('title', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('default_role_id')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->foreign('default_role_id')->references('role_id')->on('roles')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('user_id')->on('user')->nullOnDelete();
        });

        Schema::create('course_application_form_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('course_application_forms')->cascadeOnDelete();
        });

        Schema::create('course_application_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->string('field_key', 80);
            $table->string('type', 40);
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('step_id')->references('id')->on('course_application_form_steps')->cascadeOnDelete();
            $table->unique(['step_id', 'field_key'], 'course_app_fields_step_key_unique');
        });

        Schema::create('course_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('form_id');
            $table->string('status', 30)->default('pending_review');
            $table->json('snapshot');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->text('overall_rejection_note')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->foreign('form_id')->references('id')->on('course_application_forms')->cascadeOnDelete();
            $table->index(['course_id', 'status', 'submitted_at']);
            $table->index(['user_id', 'course_id']);
        });

        Schema::create('course_application_field_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('field_key', 80);
            $table->string('status', 20)->default('accepted');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('application_id', 'course_app_field_reviews_app_fk')
                ->references('id')
                ->on('course_applications')
                ->cascadeOnDelete();
            $table->unique(['application_id', 'field_key'], 'course_app_field_reviews_unique');
        });

        Schema::create('course_user_application_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('application_status', 30)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->unique(['user_id', 'course_id'], 'course_user_app_status_unique');
        });

        Schema::create('course_application_review_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('template_key', 50);
            $table->string('locale', 5);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->unique(['course_id', 'template_key', 'locale'], 'course_app_review_templates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_application_review_templates');
        Schema::dropIfExists('course_user_application_status');
        Schema::dropIfExists('course_application_field_reviews');
        Schema::dropIfExists('course_applications');
        Schema::dropIfExists('course_application_form_fields');
        Schema::dropIfExists('course_application_form_steps');
        Schema::dropIfExists('course_application_forms');
    }
};
