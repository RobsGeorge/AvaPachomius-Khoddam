<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_task_definitions')) {
            return;
        }

        Schema::create('scheduled_task_definitions', function (Blueprint $table) {
            $table->id('definition_id');
            $table->string('task_key', 80)->unique();
            $table->string('label_en', 120);
            $table->string('label_ar', 120);
            $table->string('description_en', 500)->nullable();
            $table->string('description_ar', 500)->nullable();
            $table->string('command', 120);
            $table->json('parameters')->nullable();
            $table->string('cron_expression', 120);
            $table->string('timezone', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_definitions');
    }
};
