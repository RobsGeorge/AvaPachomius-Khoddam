<?php

use App\Services\ProjectAdminService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production expand: teams added from Projects Management after the assessment
 * was created never received the shared three-link checklist. Copy sibling
 * slots (or seed the canonical link phases) onto those empty teams. Additive
 * only — existing deliverable rows are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || ! Schema::hasTable('project_deliverables')) {
            return;
        }

        if (! class_exists(ProjectAdminService::class)) {
            return;
        }

        app(ProjectAdminService::class)->backfillMissingSharedWorkflow();
    }

    public function down(): void
    {
        // Non-destructive: leave copied slots in place.
    }
};
