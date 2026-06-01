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
        Schema::create('content', function (Blueprint $table) {
            $table->id('content_id');
            $table->string('title', 30);
            $table->string('content_location', 255);
            $table->string('session_title')->nullable();
            $table->date('session_date')->nullable();
            $table->string('lecture_name')->nullable();
            $table->string('speaker_name')->nullable();
            $table->string('audio_link')->nullable();
            $table->string('slides_link')->nullable();
            $table->text('description')->nullable();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content');
    }
};
