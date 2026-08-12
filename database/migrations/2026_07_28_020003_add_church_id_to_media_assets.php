<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('media_assets', 'church_id', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};
