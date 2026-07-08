<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('portal_settings', 'theme_colors_draft')) {
                $table->json('theme_colors_draft')->nullable()->after('profile_photo_gate_enabled_at');
            }
            if (! Schema::hasColumn('portal_settings', 'theme_colors_published')) {
                $table->json('theme_colors_published')->nullable()->after('theme_colors_draft');
            }
            if (! Schema::hasColumn('portal_settings', 'theme_colors_published_at')) {
                $table->timestamp('theme_colors_published_at')->nullable()->after('theme_colors_published');
            }
            if (! Schema::hasColumn('portal_settings', 'theme_colors_published_by_user_id')) {
                $table->unsignedBigInteger('theme_colors_published_by_user_id')->nullable()->after('theme_colors_published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $columns = [
                'theme_colors_draft',
                'theme_colors_published',
                'theme_colors_published_at',
                'theme_colors_published_by_user_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('portal_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
