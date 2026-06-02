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
}
