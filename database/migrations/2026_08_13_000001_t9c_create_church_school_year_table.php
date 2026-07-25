<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T9c expand — church school-year season + optional service link.
 * Calendar/orchestration only; no global roster mutate. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('church_school_year', function (Blueprint $table) {
            $table->id('church_school_year_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('label', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('planned')->index(); // planned|active|closing|closed
            $table->timestamp('promotion_started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('completed_service_ids')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'label']);
        });

        if (Schema::hasTable('service')) {
            MigrationSupport::addColumn('service', 'church_school_year_id', function (Blueprint $table) {
                $table->unsignedBigInteger('church_school_year_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Expand-only.
    }
};
