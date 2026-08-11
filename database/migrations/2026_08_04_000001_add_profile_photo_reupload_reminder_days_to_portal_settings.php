<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('profile_photo_reupload_reminder_days')
                ->default(2)
                ->after('profile_photo_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->dropColumn('profile_photo_reupload_reminder_days');
        });
    }
};
