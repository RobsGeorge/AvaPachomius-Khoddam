<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3-expand — backfill existing roles and grants into the main church (Tenant Zero).
 * Idempotent (only touches NULLs). Platform templates (`is_template=true` with no
 * course/service) stay NULL so they remain clone sources for every church.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church')) {
            return;
        }

        $mainId = DB::table('church')->where('slug', config('tenancy.main_slug'))->value('church_id');
        if (! $mainId) {
            return;
        }

        if (Schema::hasColumn('roles', 'church_id')) {
            DB::table('roles')
                ->whereNull('church_id')
                ->where(function ($q) {
                    $q->where('is_template', false)->orWhereNull('is_template');
                })
                ->update(['church_id' => $mainId]);
        }

        if (Schema::hasColumn('user_course_role', 'church_id')) {
            DB::table('user_course_role')->whereNull('church_id')->update(['church_id' => $mainId]);
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
