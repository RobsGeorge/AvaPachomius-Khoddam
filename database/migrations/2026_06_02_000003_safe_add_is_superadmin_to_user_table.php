<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addBooleanColumn('user', 'is_superadmin', false, 'is_verified');
    }

    public function down(): void
    {
        // Brownfield-safe deploys do not drop columns on rollback.
    }
};
