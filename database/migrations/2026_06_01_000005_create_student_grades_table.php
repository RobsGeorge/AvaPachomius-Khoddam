<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id('grade_id');
            $table->foreignId('item_id')->constrained('grade_items', 'item_id')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade');
            $table->decimal('score', 7, 2)->nullable();   // null = not yet graded
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('graded_by_id')->nullable();
            $table->foreign('graded_by_id')->references('user_id')->on('user')->onDelete('set null');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
