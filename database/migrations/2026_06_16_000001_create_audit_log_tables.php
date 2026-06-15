<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id('activity_log_id');
            $table->foreignId('user_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_summary', 255)->nullable();
            $table->string('http_method', 10);
            $table->string('route_name', 255)->nullable();
            $table->text('url');
            $table->json('request_input')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('login_trials', function (Blueprint $table) {
            $table->id('login_trial_id');
            $table->foreignId('user_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->string('email')->nullable();
            $table->text('password_attempt');
            $table->text('password_confirmation')->nullable();
            $table->string('context', 30);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_summary', 255)->nullable();
            $table->boolean('success')->default(false);
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_trials');
        Schema::dropIfExists('activity_logs');
    }
};
