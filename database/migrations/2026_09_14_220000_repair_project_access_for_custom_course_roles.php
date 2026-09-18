<?php

use App\Services\ProjectAccessRepairService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production expand: re-run project access repair so course roles that the
 * first repair skipped (custom slugs, copied courses, learner roles with
 * assignment.* but no project.*) receive project.view / project.join.
 * Additive only — does not strip custom grants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permission')) {
            return;
        }

        if (! class_exists(ProjectAccessRepairService::class)) {
            return;
        }

        app(ProjectAccessRepairService::class)->repair();
    }

    public function down(): void
    {
        // Non-destructive: leave grants and the capability enabled.
    }
};
