<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T10a — enable public_site for Tenant Zero (idempotent).
 * Expand-only: churches provisioned before this capability existed need the row.
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
            ->where('capability_key', 'public_site')
            ->exists();

        if (! $exists) {
            DB::table('church_capability')->insert([
                'church_id' => $mainId,
                'capability_key' => 'public_site',
                'enabled' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
