<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'created_at')) {
            return;
        }

        if (! Schema::hasTable('registration_applications')) {
            return;
        }

        DB::table('user')
            ->whereNull('created_at')
            ->orderBy('user_id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $submitted = DB::table('registration_applications')
                        ->where('user_id', $user->user_id)
                        ->orderBy('submitted_at')
                        ->value('submitted_at');

                    if ($submitted) {
                        DB::table('user')
                            ->where('user_id', $user->user_id)
                            ->update(['created_at' => $submitted, 'updated_at' => $submitted]);
                    }
                }
            }, 'user_id');
    }

    public function down(): void
    {
        // Non-destructive backfill; no rollback.
    }
};
