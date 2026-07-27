<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usage_rollups')) {
            return;
        }

        Schema::create('usage_rollups', function (Blueprint $table) {
            $table->id();
            $table->timestamp('bucket_start');
            $table->unsignedBigInteger('church_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('unique_sessions')->default(0);
            $table->timestamps();

            $table->unique(['bucket_start', 'church_id', 'service_id'], 'usage_rollups_bucket_unique');
            $table->index(['church_id', 'bucket_start']);
            $table->index(['service_id', 'bucket_start']);
        });
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};
