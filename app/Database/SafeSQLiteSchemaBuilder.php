<?php

namespace App\Database;

use Illuminate\Database\Schema\SQLiteBuilder;

class SafeSQLiteSchemaBuilder extends SQLiteBuilder
{
    use GuardsExistingSchema;
}
