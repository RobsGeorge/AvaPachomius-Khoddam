<?php

namespace App\Database;

use Closure;
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

        parent::table($table, $callback);
    }
}
