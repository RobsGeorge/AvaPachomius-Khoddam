<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3 — student-visible team change history (append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('project_membership_events', function (Blueprint $table) {
            $table->id('project_membership_event_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('event', 32);
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(
                ['project_id', 'occurred_at'],
                'project_membership_events_team_time_idx'
            );
        });
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
