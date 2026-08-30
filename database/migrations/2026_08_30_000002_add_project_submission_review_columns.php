<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3 — instructor feedback on team deliverable submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_deliverable_submissions')) {
            return;
        }

        Schema::table('project_deliverable_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('project_deliverable_submissions', 'instructor_feedback')) {
                $table->text('instructor_feedback')->nullable();
            }
            if (! Schema::hasColumn('project_deliverable_submissions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('project_deliverable_submissions', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
