<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy existing projects.requirements into the four team-brief columns.
     * Additive data only — requirements is never dropped or cleared.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'brief_purpose')) {
            return;
        }

        Project::backfillLegacyBriefFields();
    }

    public function down(): void
    {
        // Intentionally empty: clearing brief columns would hide mapped text.
    }
};
