<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id('announcement_id');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_mode', 20)->default('course');
            $table->json('channels');
            $table->string('status', 20)->default('draft');
            $table->timestamp('banner_starts_at')->nullable();
            $table->timestamp('banner_ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('announcement_target_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->foreign('announcement_id')->references('announcement_id')->on('announcements')->cascadeOnDelete();
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id('delivery_id');
            $table->unsignedBigInteger('announcement_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->foreign('announcement_id')->references('announcement_id')->on('announcements')->cascadeOnDelete();
        });

        Schema::create('announcement_revisions', function (Blueprint $table) {
            $table->id('revision_id');
            $table->unsignedBigInteger('announcement_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action', 30);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_revisions');
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcement_target_users');
        Schema::dropIfExists('announcements');
    }
};
