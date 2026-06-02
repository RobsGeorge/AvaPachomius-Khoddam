<?php

namespace App\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder;

class SafeSchemaBuilder extends Builder
{
    public function create($table, Closure $callback)
    {
        if ($this->hasTable($table)) {
            return;
        }

        parent::create($table, $callback);
    }

    public function table($table, Closure $callback)
    {
        if (! $this->hasTable($table)) {
            return;
        }

        try {
            parent::table($table, $callback);
        } catch (QueryException $e) {
            if (! self::isBenignSchemaError($e)) {
                throw $e;
            }
        }
    }

    private static function isBenignSchemaError(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate column name')
            || str_contains($message, '1060')
            || str_contains($message, 'duplicate column');
    }
}
