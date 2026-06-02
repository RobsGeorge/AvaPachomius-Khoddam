<?php

namespace App\Database;

use Illuminate\Support\Facades\Schema;

final class SchemaGuards
{
    public static function createTableIfMissing(string $table, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    /**
     * Create a legacy table if missing, then rename `id` -> custom PK when needed.
     */
    public static function createLegacyTable(string $table, string $primaryKey, callable $callback): void
    {
        self::createTableIfMissing($table, $callback);
        LegacyPrimaryKeys::normalize($table, $primaryKey);
    }
}
