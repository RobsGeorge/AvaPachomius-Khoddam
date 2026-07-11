<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 60);
            $table->string('title');
            $table->text('body');
            $table->string('action_url', 500)->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('dedupe_key', 191);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->unique(['user_id', 'dedupe_key'], 'user_notifications_dedupe_unique');
            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 60);
            $table->boolean('portal_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->unique(['user_id', 'type'], 'user_notification_prefs_unique');
        });

        Schema::create('user_notification_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('remind_at');
            $table->string('recurrence', 20)->default('once');
            $table->json('channels')->nullable();
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->index(['remind_at', 'last_fired_at']);
        });

        Schema::create('notification_whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_notification_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('provider_message_id', 120)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('user_notification_id')->references('id')->on('user_notifications')->nullOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });

        if (Schema::hasTable('assignment_submission') && ! Schema::hasColumn('assignment_submission', 'graded_at')) {
            Schema::table('assignment_submission', function (Blueprint $table) {
                $table->timestamp('graded_at')->nullable()->after('points_earned');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_whatsapp_deliveries');
        Schema::dropIfExists('user_notification_reminders');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('user_notifications');

        if (Schema::hasTable('assignment_submission') && Schema::hasColumn('assignment_submission', 'graded_at')) {
            Schema::table('assignment_submission', function (Blueprint $table) {
                $table->dropColumn('graded_at');
            });
        }
    }
};
