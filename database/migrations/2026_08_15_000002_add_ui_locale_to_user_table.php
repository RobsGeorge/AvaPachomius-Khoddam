<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addStringColumn('user', 'ui_locale', 8, true, 'communication_locale');
    }

    public function down(): void
    {
        // Brownfield-safe migrations do not drop columns.
    }
};
