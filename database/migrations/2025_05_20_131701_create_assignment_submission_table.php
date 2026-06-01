<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submission', function (Blueprint $table) {
            $table->id('submission_id');
            $table->foreignId('assignment_id')
                  ->constrained('assignments', 'assignment_id')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('user', 'user_id')
                  ->onDelete('cascade');
            $table->text('submission_content');
            $table->string('file_path')->nullable();
            $table->integer('points_earned')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submission');
    }
};
