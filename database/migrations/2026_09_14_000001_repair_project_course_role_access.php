<?php

use App\Services\ProjectAccessRepairService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Production expand: enable the projects capability and merge missing
 * project.view / project.join / project.manage keys onto existing course
 * role clones so students see Projects and course staff can open Manage.
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
