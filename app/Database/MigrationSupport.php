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
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($table, $column, $default, $after) {
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
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($table, $column, $length, $nullable, $after) {
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
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($table, $column, $nullable, $after) {
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
        self::addColumn($table, $column, function (Blueprint $blueprint) use ($table, $column, $nullable, $after) {
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

    public static function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();

            $row = $connection->selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
                [$database, $table, $name, 'FOREIGN KEY']
            );

            return $row !== null;
        }

        if ($driver === 'sqlite') {
            $column = self::foreignKeyColumnFromName($table, $name);

            if ($column === null) {
                return false;
            }

            $escapedTable = str_replace("'", "''", $table);
            $rows = $connection->select("PRAGMA foreign_key_list('{$escapedTable}')");

            foreach ($rows as $row) {
                $from = is_object($row) ? ($row->from ?? null) : ($row['from'] ?? null);

                if ($from === $column) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private static function foreignKeyColumnFromName(string $table, string $name): ?string
    {
        $prefix = $table.'_';
        $suffix = '_foreign';

        if (! str_starts_with($name, $prefix) || ! str_ends_with($name, $suffix)) {
            return null;
        }

        return substr($name, strlen($prefix), -strlen($suffix));
    }
}
