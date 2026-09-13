<?php

use App\Services\ProjectExampleSeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production expand: attach a separate example project assessment (three teams,
 * distinct titles + per-team requirements) to Tenant Zero's current course.
 * Idempotent — a second migrate / command run does not duplicate the row.
 * Additive only; never drops or renames existing project data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_assessments') || ! Schema::hasTable('course')) {
            return;
        }

        if (! app()->bound(ProjectExampleSeedService::class) && ! class_exists(ProjectExampleSeedService::class)) {
            return;
        }

        app(ProjectExampleSeedService::class)->seed();
    }

    public function down(): void
    {
        // Non-destructive: leave the example assessment in place.
    }
};
