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
        Schema::table('content', function (Blueprint $table) {
            // Fix the typo in the existing column name
            $table->renameColumn('content_lcation', 'content_location');
            
            // Add new columns for enhanced content functionality
            $table->string('session_title')->nullable()->after('title');
            $table->date('session_date')->nullable()->after('session_title');
            $table->string('lecture_name')->nullable()->after('session_date');
            $table->string('speaker_name')->nullable()->after('lecture_name');
            $table->string('audio_link')->nullable()->after('speaker_name');
            $table->string('slides_link')->nullable()->after('audio_link');
            $table->text('description')->nullable()->after('slides_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content', function (Blueprint $table) {
            $table->renameColumn('content_location', 'content_lcation');
            $table->dropColumn([
                'session_title',
                'session_date', 
                'lecture_name',
                'speaker_name',
                'audio_link',
                'slides_link',
                'description'
            ]);
        });
    }
}; 