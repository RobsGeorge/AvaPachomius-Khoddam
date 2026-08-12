<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('observability_events')) {
            return;
        }

        Schema::create('observability_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('severity', 16);
            $table->string('category', 32);
            $table->string('fingerprint', 64);
            $table->string('message', 2000);
            $table->string('exception_class')->nullable();
            $table->text('stack_excerpt')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('method', 16)->nullable();
            $table->string('route_name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('church_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->uuid('request_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['fingerprint', 'occurred_at']);
            $table->index(['church_id', 'occurred_at']);
            $table->index(['category', 'severity', 'occurred_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};
