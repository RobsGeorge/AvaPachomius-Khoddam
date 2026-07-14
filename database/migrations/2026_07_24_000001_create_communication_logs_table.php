<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_logs')) {
            return;
        }

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable()->index();
            $table->string('recipient_mobile', 40)->nullable()->index();
            $table->string('channel', 32)->index();
            $table->string('status', 32)->index();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('sent_by_user_id')->nullable()->index();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('opened_at')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['course_id', 'sent_at']);
            $table->index(['service_id', 'sent_at']);
            $table->index(['user_id', 'channel', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
