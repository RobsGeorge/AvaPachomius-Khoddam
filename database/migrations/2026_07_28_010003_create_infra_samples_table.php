<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('infra_samples')) {
            return;
        }

        Schema::create('infra_samples', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sampled_at');
            $table->string('host', 191);
            $table->float('load_1')->nullable();
            $table->float('load_5')->nullable();
            $table->float('cpu_pct')->nullable();
            $table->float('mem_used_mb')->nullable();
            $table->float('mem_total_mb')->nullable();
            $table->float('disk_used_pct')->nullable();
            $table->unsignedInteger('php_fpm_active')->nullable();
            $table->string('source', 64);
            $table->timestamps();

            $table->index(['host', 'sampled_at']);
            $table->index('sampled_at');
        });
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};
