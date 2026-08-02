<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_settings')
            || Schema::hasColumn('portal_settings', 'profile_photo_reupload_reminder_days')) {
            return;
        }

        Schema::table('portal_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('profile_photo_reupload_reminder_days')
                ->default(2)
                ->after('profile_photo_gate_enabled_at');
        });
    }

    public function down(): void
    {
        // Additive migration: contractions are intentionally deferred.
    }
};
