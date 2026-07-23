<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_task_settings')) {
            Schema::create('scheduled_task_settings', function (Blueprint $table) {
                $table->id('setting_id');
                $table->string('task_key', 80)->unique();
                $table->boolean('enabled')->default(true);
                $table->string('cron_expression', 120)->nullable();
                $table->foreignId('updated_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scheduled_task_runs')) {
            Schema::create('scheduled_task_runs', function (Blueprint $table) {
                $table->id('run_id');
                $table->string('task_key', 80)->index();
                $table->string('status', 20)->default('running');
                $table->string('trigger', 20)->default('scheduled');
                $table->unsignedInteger('exit_code')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->longText('output')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('finished_at')->nullable();
                $table->foreignId('triggered_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
        Schema::dropIfExists('scheduled_task_settings');
    }
};
