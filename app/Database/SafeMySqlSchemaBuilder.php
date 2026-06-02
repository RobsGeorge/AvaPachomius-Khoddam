<?php

namespace App\Database;

use Illuminate\Database\Schema\MySqlBuilder;

class SafeMySqlSchemaBuilder extends MySqlBuilder
{
    use GuardsExistingSchema;
}
