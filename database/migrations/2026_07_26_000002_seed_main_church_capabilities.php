<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T2 — enable every catalog capability for the main church (Tenant Zero) so the existing
 * app is unchanged. Idempotent and non-clobbering: only inserts missing rows, so an admin
 * who later disables a capability is not overridden on re-run.
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

        foreach (array_keys((array) config('capabilities')) as $key) {
            $exists = DB::table('church_capability')
                ->where('church_id', $mainId)
                ->where('capability_key', $key)
                ->exists();

            if (! $exists) {
                DB::table('church_capability')->insert([
                    'church_id' => $mainId,
                    'capability_key' => $key,
                    'enabled' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
