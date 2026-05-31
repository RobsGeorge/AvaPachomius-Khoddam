<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecture_materials', function (Blueprint $table) {
            $table->id('material_id');
            $table->foreignId('lecture_id')->constrained('lectures', 'lecture_id')->onDelete('cascade');
            $table->string('title', 150);
            $table->string('link', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecture_materials');
    }
};
