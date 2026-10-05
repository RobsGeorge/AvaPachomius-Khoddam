<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Expand: an announcement may deep-link to a feedback survey so student
 * clicks land on the form instead of a context-picker bounce.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('announcements', 'survey_id', function (Blueprint $table) {
            $table->unsignedBigInteger('survey_id')->nullable()->index()->after('course_id');
        });
    }

    public function down(): void
    {
        // Additive expand — keep the column.
    }
};
