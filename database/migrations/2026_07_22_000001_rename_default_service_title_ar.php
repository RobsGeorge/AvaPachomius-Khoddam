<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename Default Service Arabic title to match lang/service.default_name.
 * Additive data fix only — no schema contraction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service')) {
            return;
        }

        DB::table('service')
            ->where('title_ar', 'الخدمة الافتراضية')
            ->update([
                'title_ar' => 'الخدمة الاساسية',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('service')) {
            return;
        }

        DB::table('service')
            ->where('title_ar', 'الخدمة الاساسية')
            ->where('title', 'Default Service')
            ->update([
                'title_ar' => 'الخدمة الافتراضية',
                'updated_at' => now(),
            ]);
    }
};
