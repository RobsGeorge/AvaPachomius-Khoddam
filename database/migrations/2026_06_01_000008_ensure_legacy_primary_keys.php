<?php

use App\Database\LegacySchemaSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegacySchemaSync::syncAll();
    }

    public function down(): void
    {
        // Irreversible on production data; down is intentionally empty.
    }
};
