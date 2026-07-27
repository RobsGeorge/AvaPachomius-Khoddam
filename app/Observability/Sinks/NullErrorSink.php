<?php

namespace App\Observability\Sinks;

use App\Observability\Contracts\ErrorSink;

class NullErrorSink implements ErrorSink
{
    public function send(array $event): void
    {
        // Intentionally empty — DB persist (when enabled) is the source of truth.
    }
}
