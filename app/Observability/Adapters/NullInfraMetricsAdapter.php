<?php

namespace App\Observability\Adapters;

use App\Observability\Contracts\InfraMetricsAdapter;

class NullInfraMetricsAdapter implements InfraMetricsAdapter
{
    public function sample(): ?array
    {
        return null;
    }
}
