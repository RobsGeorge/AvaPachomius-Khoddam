<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_content', function (Blueprint $table) {
            $table->id('module_content_id');
            $table->foreignId('module_id')->constrained('module', 'module_id');
            $table->foreignId('content_id')->constrained('content', 'content_id');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_content');
    }
};
