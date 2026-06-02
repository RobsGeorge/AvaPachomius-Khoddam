<?php

namespace App\Database;

use Closure;
use Illuminate\Database\Schema\Blueprint;

final class SafeBlueprint
{
    /**
     * Run a column change only when the table exists and the column is missing.
     */
    public static function addColumnIfMissing(
        string $table,
        string $column,
        Closure $callback
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
            return;
        }

        \Illuminate\Support\Facades\Schema::table($table, $callback);
    }

    /**
     * @param  list<string>  $columns
     */
    public static function addColumnsIfMissing(
        string $table,
        array $columns,
        Closure $callback
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                return;
            }
        }

        \Illuminate\Support\Facades\Schema::table($table, $callback);
    }
}
