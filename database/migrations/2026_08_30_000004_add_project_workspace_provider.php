<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v3 — workspace provider deep-links (no OAuth).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'workspace_provider')) {
                $table->string('workspace_provider', 16)->default('custom');
            }
        });
    }

    public function down(): void
    {
        // Expand-contract: drop only in Phase 5 contraction PRs.
    }
};
