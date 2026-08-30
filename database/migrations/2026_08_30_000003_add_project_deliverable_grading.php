<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3 — deliverable-level grading mode + per-deliverable scores.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'grading_mode')) {
                    $table->string('grading_mode', 16)->default('rubric');
                }
            });
        }

        if (Schema::hasTable('project_deliverables')) {
            Schema::table('project_deliverables', function (Blueprint $table) {
                if (! Schema::hasColumn('project_deliverables', 'max_points')) {
                    $table->decimal('max_points', 8, 2)->nullable();
                }
            });
        }

        SchemaGuards::createTableIfMissing('project_deliverable_grades', function (Blueprint $table) {
            $table->id('project_deliverable_grade_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('project_deliverable_id')->index();
            $table->decimal('points', 8, 2);
            $table->unsignedBigInteger('graded_by_user_id')->nullable()->index();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['project_id', 'project_deliverable_id'],
                'project_deliverable_grades_team_unique'
            );
        });
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
