<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand: surveys may be named or anonymous, and a blocking survey may
 * hide one specific exam or project assessment in its module.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_surveys')) {
            return;
        }

        Schema::table('feedback_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback_surveys', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(true);
            }
            if (! Schema::hasColumn('feedback_surveys', 'blocks_exam_id')) {
                $table->unsignedBigInteger('blocks_exam_id')->nullable()->index();
            }
            if (! Schema::hasColumn('feedback_surveys', 'blocks_project_assessment_id')) {
                $table->unsignedBigInteger('blocks_project_assessment_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Additive expand — keep columns.
    }
};
