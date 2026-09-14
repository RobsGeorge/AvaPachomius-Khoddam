<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-team project brief: four labeled 255-char fields plus the existing title.
 * Additive only — do not drop requirements.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'brief_main_title')) {
                $table->string('brief_main_title', 255)->nullable()->after('title');
            }
            if (! Schema::hasColumn('projects', 'brief_audience')) {
                $table->string('brief_audience', 255)->nullable()->after('brief_main_title');
            }
            if (! Schema::hasColumn('projects', 'brief_environment')) {
                $table->string('brief_environment', 255)->nullable()->after('brief_audience');
            }
            if (! Schema::hasColumn('projects', 'brief_purpose')) {
                $table->string('brief_purpose', 255)->nullable()->after('brief_environment');
            }
        });
    }

    public function down(): void
    {
        // Expand-only: leave columns in place.
    }
};
