<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->foreignId('course_id')->constrained('course', 'course_id')->onDelete('cascade');
            $table->string('type', 30);          // exam, quiz, presentation, project, attendance, other
            $table->string('name', 100);
            $table->decimal('weight_percentage', 5, 2);
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_categories');
    }
};
