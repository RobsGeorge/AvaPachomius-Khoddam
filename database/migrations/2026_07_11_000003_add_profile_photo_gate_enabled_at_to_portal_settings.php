<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->timestamp('profile_photo_gate_enabled_at')->nullable()->after('profile_photo_gate_enabled');
        });

        DB::table('portal_settings')
            ->whereNull('profile_photo_gate_enabled_at')
            ->update(['profile_photo_gate_enabled_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('portal_settings', function (Blueprint $table) {
            $table->dropColumn('profile_photo_gate_enabled_at');
        });
    }
};
