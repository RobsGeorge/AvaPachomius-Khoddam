<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * T10c expand — curated homepage CMS (church_site, sections, media). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('church_site', function (Blueprint $table) {
            $table->id('church_site_id');
            $table->unsignedBigInteger('church_id')->unique();
            $table->json('theme_draft')->nullable();
            $table->json('theme_published')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('church_site_section', function (Blueprint $table) {
            $table->id('church_site_section_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('church_site_id')->index();
            $table->string('type', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled_draft')->default(true);
            $table->boolean('enabled_published')->default(false);
            $table->json('content_draft')->nullable();
            $table->json('content_published')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('church_media', function (Blueprint $table) {
            $table->id('church_media_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('path');
            $table->string('alt_ar')->nullable();
            $table->string('alt_en')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Expand-only.
    }
};
