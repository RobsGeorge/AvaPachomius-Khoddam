<?php

use App\Services\ProjectAccessRepairService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production expand: keep the Projects permission group visible on the
 * course Roles hub matrix (course admins could not assign project.* when
 * the group was hidden). Re-runs the additive project access repair.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_groups')) {
            return;
        }

        if (! class_exists(ProjectAccessRepairService::class)) {
            return;
        }

        app(ProjectAccessRepairService::class)->repair();
    }

    public function down(): void
    {
        // Non-destructive: leave visibility and grants in place.
    }
};
