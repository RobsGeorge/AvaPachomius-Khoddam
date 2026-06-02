<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class MigrationSupport
{
    public static function addBooleanColumn(
        string $table,
        string $column,
        bool $default = false,
        ?string $after = null
    ): void {
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($column, $default, $after) {
            $definition = $blueprint->boolean($column)->default($default);

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    public static function addStringColumn(
        string $table,
        string $column,
        int $length,
        bool $nullable = true,
        ?string $after = null
    ): void {
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($column, $length, $nullable, $after) {
            $definition = $blueprint->string($column, $length);

            if ($nullable) {
                $definition->nullable();
            }

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    public static function addTextColumn(
        string $table,
        string $column,
        bool $nullable = true,
        ?string $after = null
    ): void {
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($column, $nullable, $after) {
            $definition = $blueprint->text($column);

            if ($nullable) {
                $definition->nullable();
            }

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    public static function addDateColumn(
        string $table,
        string $column,
        bool $nullable = true,
        ?string $after = null
    ): void {
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($column, $nullable, $after) {
            $definition = $blueprint->date($column);

            if ($nullable) {
                $definition->nullable();
            }

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });
    }

    public static function addColumn(string $table, string $column, callable $callback): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $callback);
    }
}
