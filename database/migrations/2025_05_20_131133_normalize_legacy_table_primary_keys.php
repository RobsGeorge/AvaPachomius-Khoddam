<?php

use App\Database\LegacyPrimaryKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegacyPrimaryKeys::normalizeAll();
    }

    public function down(): void
    {
        // Irreversible on production data; down is intentionally empty.
    }
};
