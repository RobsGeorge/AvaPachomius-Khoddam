<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'application_status')) {
            return;
        }

        DB::table('user')
            ->where('registration_completed', true)
            ->where('is_verified', true)
            ->whereNull('application_status')
            ->update(['application_status' => 'approved']);
    }

    public function down(): void
    {
        // Data backfill only.
    }
};
