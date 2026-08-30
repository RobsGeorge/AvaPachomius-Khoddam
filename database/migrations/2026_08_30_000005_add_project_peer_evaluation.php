<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3 — anonymous informational peer evaluation.
 * Scores never write project_member_grades.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'peer_eval_enabled')) {
                    $table->boolean('peer_eval_enabled')->default(false);
                }
                if (! Schema::hasColumn('project_assessments', 'peer_eval_opens_at')) {
                    $table->timestamp('peer_eval_opens_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'peer_eval_closes_at')) {
                    $table->timestamp('peer_eval_closes_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'peer_eval_scale_max')) {
                    $table->unsignedTinyInteger('peer_eval_scale_max')->default(5);
                }
                if (! Schema::hasColumn('project_assessments', 'peer_eval_prompt')) {
                    $table->text('peer_eval_prompt')->nullable();
                }
            });
        }

        SchemaGuards::createTableIfMissing('project_peer_ratings', function (Blueprint $table) {
            $table->id('project_peer_rating_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('rater_user_id')->index();
            $table->unsignedBigInteger('ratee_user_id')->index();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(
                ['project_id', 'rater_user_id', 'ratee_user_id'],
                'project_peer_ratings_unique'
            );
        });
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
