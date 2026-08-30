<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3.1 — cross-team peer evaluation (replaces within-team).
 * Additive: assessment min/max picks + ratee_project_id; replace unique key
 * so multiple team ratings per rater work (legacy ratee_user_id kept, unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'peer_eval_min_picks')) {
                    $table->unsignedTinyInteger('peer_eval_min_picks')->default(1);
                }
                if (! Schema::hasColumn('project_assessments', 'peer_eval_max_picks')) {
                    $table->unsignedTinyInteger('peer_eval_max_picks')->default(3);
                }
            });
        }

        if (! Schema::hasTable('project_peer_ratings')) {
            return;
        }

        Schema::table('project_peer_ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('project_peer_ratings', 'ratee_project_id')) {
                $table->unsignedBigInteger('ratee_project_id')->nullable()->index();
            }
        });

        // Old unique was (project_id, rater_user_id, ratee_user_id) for within-team.
        // Cross-team uniqueness is per assessment + rater + ratee team.
        if (Schema::hasIndex('project_peer_ratings', 'project_peer_ratings_unique')) {
            Schema::table('project_peer_ratings', function (Blueprint $table) {
                $table->dropUnique('project_peer_ratings_unique');
            });
        }

        if (! Schema::hasIndex('project_peer_ratings', 'project_peer_ratings_team_unique')) {
            Schema::table('project_peer_ratings', function (Blueprint $table) {
                $table->unique(
                    ['project_assessment_id', 'rater_user_id', 'ratee_project_id'],
                    'project_peer_ratings_team_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
