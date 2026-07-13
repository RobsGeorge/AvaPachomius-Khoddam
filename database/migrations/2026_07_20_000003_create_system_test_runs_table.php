<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_test_runs')) {
            return;
        }

        Schema::create('system_test_runs', function (Blueprint $table) {
            $table->id('test_run_id');
            // Suite key: unit|feature|smoke|api|notifications|mail|tenancy|load|all
            $table->string('suite', 40);
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            // Correlates every suite executed within a single "run all" invocation.
            $table->string('batch_id', 36)->nullable()->index();
            $table->text('summary')->nullable();
            $table->longText('output')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('triggered_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_test_runs');
    }
};
