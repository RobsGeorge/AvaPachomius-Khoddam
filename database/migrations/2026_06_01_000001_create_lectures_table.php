<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lectures', function (Blueprint $table) {
            $table->id('lecture_id');
            $table->foreignId('module_id')->constrained('modules', 'module_id')->onDelete('cascade');
            $table->string('title', 150);
            $table->unsignedSmallInteger('week_number');
            $table->date('lecture_date')->nullable();
            $table->string('video_link', 500)->nullable();
            $table->string('slides_link', 500)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lectures');
    }
};
