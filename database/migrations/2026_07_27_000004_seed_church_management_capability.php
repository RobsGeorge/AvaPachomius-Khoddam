<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3 — enable the new church_management capability for Tenant Zero (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church') || ! Schema::hasTable('church_capability')) {
            return;
        }

        $mainId = DB::table('church')->where('slug', config('tenancy.main_slug'))->value('church_id');
        if (! $mainId) {
            return;
        }

        $exists = DB::table('church_capability')
            ->where('church_id', $mainId)
            ->where('capability_key', 'church_management')
            ->exists();

        if (! $exists) {
            DB::table('church_capability')->insert([
                'church_id' => $mainId,
                'capability_key' => 'church_management',
                'enabled' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
