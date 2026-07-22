<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T8b expand — per-row optimistic locking for attendance (§14 G7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance')) {
            return;
        }

        MigrationSupport::addColumn('attendance', 'lock_version', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0);
        });
    }

    public function down(): void
    {
        // Expand-only.
    }
};
