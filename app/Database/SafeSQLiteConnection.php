<?php

namespace App\Database;

use Illuminate\Database\SQLiteConnection;

class SafeSQLiteConnection extends SQLiteConnection
{
    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }

        return new SafeSchemaBuilder($this);
    }
}
