<?php

use App\Models\ChurchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * T8a added nullable `service.slug` and stamped only the first Tenant Zero
 * row (`servants-prep`). Later T8b made slug the route key, so any leftover
 * NULL slug 500s `/superadmin/courses` when the services panel generates URLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service') || ! Schema::hasColumn('service', 'slug')) {
            return;
        }

        ChurchService::backfillMissingSlugs();
    }

    public function down(): void
    {
        // Expand-only: keep assigned slugs.
    }
};
