<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v2 — additive per-team rubric. A row either overrides a shared
 * criterion for one team (`project_grade_criterion_id` set) or adds a team-only
 * criterion (`project_grade_criterion_id` null). Team-only criteria are scored
 * in their own table so the v1 score table keeps its NOT NULL criterion key.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('project_team_grade_criteria', function (Blueprint $table) {
            $table->id('project_team_grade_criterion_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('project_grade_criterion_id')->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->decimal('max_points', 8, 2)->default(0);
            $table->boolean('is_excluded')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['project_id', 'project_grade_criterion_id'],
                'project_team_grade_criteria_override_unique'
            );
        });

        SchemaGuards::createTableIfMissing('project_team_criterion_scores', function (Blueprint $table) {
            $table->id('project_team_criterion_score_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_team_grade_id')->index();
            $table->unsignedBigInteger('project_team_grade_criterion_id')->index();
            $table->decimal('points', 8, 2);
            $table->timestamps();

            $table->unique(
                ['project_team_grade_id', 'project_team_grade_criterion_id'],
                'project_team_criterion_scores_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_team_criterion_scores');
        Schema::dropIfExists('project_team_grade_criteria');
    }
};
