<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T8a expand — bind services to structure templates (slug + template + level overrides).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service')) {
            return;
        }

        MigrationSupport::addColumn('service', 'slug', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->index();
        });

        MigrationSupport::addColumn('service', 'structure_template_id', function (Blueprint $table) {
            $col = $table->unsignedBigInteger('structure_template_id')->nullable()->index();
            if (Schema::hasColumn('service', 'slug')) {
                $col->after('slug');
            }
        });

        MigrationSupport::addColumn('service', 'level_labels', function (Blueprint $table) {
            $table->json('level_labels')->nullable();
        });

        MigrationSupport::addColumn('service', 'enabled_levels', function (Blueprint $table) {
            $table->json('enabled_levels')->nullable();
        });

        MigrationSupport::addColumn('service', 'custom_field_defs', function (Blueprint $table) {
            $table->json('custom_field_defs')->nullable();
        });

        // Unique slug per church when church_id exists (expand-safe composite).
        if (Schema::hasColumn('service', 'church_id') && Schema::hasColumn('service', 'slug')) {
            Schema::table('service', function (Blueprint $table) {
                try {
                    $table->unique(['church_id', 'slug'], 'service_church_id_slug_unique');
                } catch (\Throwable) {
                    // Index may already exist on replay.
                }
            });
        }
    }

    public function down(): void
    {
        // Expand-only.
    }
};
