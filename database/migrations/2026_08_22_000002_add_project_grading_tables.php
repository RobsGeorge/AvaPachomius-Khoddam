<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project grading: criteria, team (group) grades, optional per-student overrides,
 * one-shot results announcement. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'max_points')) {
                    $table->decimal('max_points', 8, 2)->default(100);
                }
                if (! Schema::hasColumn('project_assessments', 'passing_percent')) {
                    $table->unsignedTinyInteger('passing_percent')->default(50);
                }
                if (! Schema::hasColumn('project_assessments', 'results_announced_at')) {
                    $table->timestamp('results_announced_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'results_announced_by_user_id')) {
                    $table->unsignedBigInteger('results_announced_by_user_id')->nullable()->index();
                }
            });
        }

        SchemaGuards::createTableIfMissing('project_grade_criteria', function (Blueprint $table) {
            $table->id('project_grade_criterion_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->string('title', 255);
            $table->decimal('max_points', 8, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('project_team_grades', function (Blueprint $table) {
            $table->id('project_team_grade_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->decimal('points', 8, 2);
            $table->decimal('percent', 5, 2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('graded_by_user_id')->nullable()->index();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id'], 'project_team_grades_project_unique');
        });

        SchemaGuards::createTableIfMissing('project_team_grade_scores', function (Blueprint $table) {
            $table->id('project_team_grade_score_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_team_grade_id')->index();
            $table->unsignedBigInteger('project_grade_criterion_id')->index();
            $table->decimal('points', 8, 2);
            $table->timestamps();

            $table->unique(
                ['project_team_grade_id', 'project_grade_criterion_id'],
                'project_team_grade_scores_unique'
            );
        });

        SchemaGuards::createTableIfMissing('project_member_grades', function (Blueprint $table) {
            $table->id('project_member_grade_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('points', 8, 2);
            $table->decimal('percent', 5, 2);
            $table->string('source', 16)->default('team');
            $table->unsignedBigInteger('graded_by_user_id')->nullable()->index();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['project_assessment_id', 'user_id'],
                'project_member_grades_assessment_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_member_grades');
        Schema::dropIfExists('project_team_grade_scores');
        Schema::dropIfExists('project_team_grades');
        Schema::dropIfExists('project_grade_criteria');
    }
};
