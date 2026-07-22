<?php

use App\Tenancy\EnforceChurchIdNotNull;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * T7 contract — backfill any remaining NULL church_id rows to Tenant Zero, then
 * enforce NOT NULL on tenant tables (except platform role templates on `roles`).
 *
 * MySQL: ALTER … MODIFY. SQLite (tests): backfill only — BelongsToChurch stamps on create.
 */
return new class extends Migration
{
    public function up(): void
    {
        EnforceChurchIdNotNull::backfillToMain();
        EnforceChurchIdNotNull::enforceNotNullOnMysql();
    }

    public function down(): void
    {
        EnforceChurchIdNotNull::relaxNotNullOnMysql();
    }
};
