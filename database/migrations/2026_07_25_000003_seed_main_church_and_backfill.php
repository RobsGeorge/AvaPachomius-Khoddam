<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T0 — seed the default church (Tenant Zero) and backfill all existing data into
 * it. Pure data migration: idempotent and re-runnable (whereNull update + unique
 * insertOrIgnore), so it is safe to replay on the legacy VPS database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church')) {
            return;
        }

        $slug = config('tenancy.main_slug');

        $mainId = DB::table('church')->where('slug', $slug)->value('church_id');
        if (! $mainId) {
            $mainId = DB::table('church')->insertGetId([
                'slug'       => $slug,
                'name'       => config('tenancy.main_name', 'Main'),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'church_id');
        }

        // Stamp every data-root row that is not yet assigned to a church.
        foreach (config('tenancy.tenant_tables') as $table) {
            if (Schema::hasColumn($table, 'church_id')) {
                DB::table($table)->whereNull('church_id')->update(['church_id' => $mainId]);
            }
        }

        // Give every existing user membership of the main church (shared user pool).
        if (Schema::hasTable('church_user') && Schema::hasTable('user')) {
            DB::table('user')->select('user_id')->orderBy('user_id')->chunk(500, function ($users) use ($mainId) {
                $rows = collect($users)->map(fn ($u) => [
                    'church_id' => $mainId,
                    'user_id'   => $u->user_id,
                    'status'    => 'active',
                    'joined_at' => now(),
                ])->all();

                if ($rows !== []) {
                    DB::table('church_user')->insertOrIgnore($rows); // unique(church_id,user_id) dedupes re-runs
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: leave data stamped.
    }
};
