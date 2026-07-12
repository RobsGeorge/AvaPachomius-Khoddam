<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course', function (Blueprint $table) {
            if (! Schema::hasColumn('course', 'title_ar')) {
                $table->string('title_ar', 120)->nullable()->after('title');
            }
            if (! Schema::hasColumn('course', 'title_en')) {
                $table->string('title_en', 120)->nullable()->after('title_ar');
            }
            if (! Schema::hasColumn('course', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
            if (! Schema::hasColumn('course', 'description_en')) {
                $table->text('description_en')->nullable()->after('description_ar');
            }
            if (! Schema::hasColumn('course', 'branding_theme')) {
                $table->json('branding_theme')->nullable()->after('description_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course', function (Blueprint $table) {
            foreach (['title_ar', 'title_en', 'description_ar', 'description_en', 'branding_theme'] as $column) {
                if (Schema::hasColumn('course', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
