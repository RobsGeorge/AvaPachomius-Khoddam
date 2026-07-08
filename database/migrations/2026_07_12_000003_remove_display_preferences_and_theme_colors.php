<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user') && Schema::hasColumn('user', 'font_size_preference')) {
            Schema::table('user', function (Blueprint $table) {
                $table->dropColumn('font_size_preference');
            });
        }

        if (! Schema::hasTable('portal_settings')) {
            return;
        }

        $columns = array_values(array_filter([
            'theme_colors_draft',
            'theme_colors_published',
            'theme_colors_published_at',
            'theme_colors_published_by_user_id',
        ], fn (string $column) => Schema::hasColumn('portal_settings', $column)));

        if ($columns !== []) {
            Schema::table('portal_settings', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty — feature removed.
    }
};
