<?php

namespace App\Observability\Sinks;

use App\Observability\Contracts\ErrorSink;
use Illuminate\Support\Facades\Log;

class LogErrorSink implements ErrorSink
{
    public function send(array $event): void
    {
        $level = match ($event['severity'] ?? 'error') {
            'debug' => 'debug',
            'info' => 'info',
            'warning' => 'warning',
            'critical' => 'critical',
            default => 'error',
        };

        Log::channel(config('logging.observability_channel', 'stack'))->{$level}(
            '[observability] '.$event['message'],
            [
                'category' => $event['category'] ?? null,
                'fingerprint' => $event['fingerprint'] ?? null,
                'exception_class' => $event['exception_class'] ?? null,
                'request_id' => $event['request_id'] ?? null,
                'user_id' => $event['user_id'] ?? null,
                'church_id' => $event['church_id'] ?? null,
            ]
        );
    }
}
