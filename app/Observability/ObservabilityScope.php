<?php

namespace App\Observability;

enum ObservabilityScope: string
{
    case Platform = 'platform';
    case Church = 'church';
}
