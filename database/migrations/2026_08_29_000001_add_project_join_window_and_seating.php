<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v2 — join window, seed pool and admin seating controls.
 * Additive only: legacy rows keep a null join window (treated as "always open").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'join_closes_at')) {
                    $table->dateTime('join_closes_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'seed_pool_size')) {
                    $table->unsignedSmallInteger('seed_pool_size')->nullable();
                }
            });
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (! Schema::hasColumn('projects', 'is_locked')) {
                    $table->boolean('is_locked')->default(false);
                }
                if (! Schema::hasColumn('projects', 'below_minimum')) {
                    $table->boolean('below_minimum')->default(false);
                }
                if (! Schema::hasColumn('projects', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('project_memberships')) {
            Schema::table('project_memberships', function (Blueprint $table) {
                if (! Schema::hasColumn('project_memberships', 'change_chance_used_at')) {
                    $table->timestamp('change_chance_used_at')->nullable();
                }
                if (! Schema::hasColumn('project_memberships', 'moved_by_user_id')) {
                    $table->unsignedBigInteger('moved_by_user_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Expand-only migration: contractions belong to a dedicated Phase 5 PR.
    }
};
