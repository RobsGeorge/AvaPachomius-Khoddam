<?php

namespace App\Database;

use Illuminate\Database\MySqlConnection;

class SafeMySqlConnection extends MySqlConnection
{
    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }

        return new SafeSchemaBuilder($this);
    }
}
