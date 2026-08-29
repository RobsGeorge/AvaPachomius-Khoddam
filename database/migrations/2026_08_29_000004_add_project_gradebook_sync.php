<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v2 — opt-in gradebook sync on announce. Additive only; existing
 * assessments default to off so nothing lands in the gradebook unasked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_assessments')) {
            return;
        }

        Schema::table('project_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_assessments', 'sync_to_gradebook')) {
                $table->boolean('sync_to_gradebook')->default(false);
            }
            if (! Schema::hasColumn('project_assessments', 'gradebook_item_id')) {
                $table->unsignedBigInteger('gradebook_item_id')->nullable()->index();
            }
            if (! Schema::hasColumn('project_assessments', 'gradebook_synced_at')) {
                $table->timestamp('gradebook_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Expand-only migration: contractions belong to a dedicated Phase 5 PR.
    }
};
