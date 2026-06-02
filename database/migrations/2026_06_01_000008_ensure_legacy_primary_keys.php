<?php

use App\Database\LegacyPrimaryKeys;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Safety net for brownfield databases: normalize any legacy `id` columns
     * before late migrations add foreign keys to custom primary key names.
     */
    public function up(): void
    {
        LegacyPrimaryKeys::normalizeAll();
    }

    public function down(): void
    {
        // Irreversible on production data; down is intentionally empty.
    }
};
