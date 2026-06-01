<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->foreignId('category_id')->constrained('grade_categories', 'category_id')->onDelete('cascade');
            $table->string('title', 150);
            $table->decimal('max_score', 7, 2);
            $table->date('item_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_items');
    }
};
