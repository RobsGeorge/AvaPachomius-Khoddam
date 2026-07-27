<?php

namespace App\Observability\Sinks;

use App\Observability\Contracts\ErrorSink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sentry/GlitchTip-compatible envelope sender (store API). Optional W6 sink.
 */
class SentryErrorSink implements ErrorSink
{
    public function send(array $event): void
    {
        $dsn = config('observability.sentry_dsn');
        if (! is_string($dsn) || $dsn === '') {
            return;
        }

        $parsed = $this->parseDsn($dsn);
        if ($parsed === null) {
            Log::warning('observability.sentry_dsn_invalid');

            return;
        }

        [$endpoint, $publicKey] = $parsed;

        $payload = [
            'message' => $event['message'] ?? 'observability event',
            'level' => $event['severity'] ?? 'error',
            'platform' => 'php',
            'fingerprint' => [$event['fingerprint'] ?? 'unknown'],
            'tags' => array_filter([
                'category' => $event['category'] ?? null,
                'church_id' => isset($event['church_id']) ? (string) $event['church_id'] : null,
            ]),
            'extra' => [
                'request_id' => $event['request_id'] ?? null,
                'user_id' => $event['user_id'] ?? null,
                'exception_class' => $event['exception_class'] ?? null,
            ],
            'exception' => [
                'values' => [[
                    'type' => $event['exception_class'] ?? 'Error',
                    'value' => $event['message'] ?? '',
                    'stacktrace' => [
                        'frames' => [[
                            'filename' => 'observability',
                            'function' => 'record',
                            'context_line' => $event['stack_excerpt'] ?? null,
                        ]],
                    ],
                ]],
            ],
        ];

        try {
            Http::timeout(2)
                ->withHeaders([
                    'X-Sentry-Auth' => sprintf(
                        'Sentry sentry_version=7, sentry_client=khedma-observability/1.0, sentry_key=%s',
                        $publicKey
                    ),
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('observability.sentry_send_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseDsn(string $dsn): ?array
    {
        $parts = parse_url($dsn);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['user']) || empty($parts['path'])) {
            return null;
        }

        $projectId = trim((string) $parts['path'], '/');
        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $endpoint = sprintf('%s://%s%s/api/%s/store/', $scheme, $parts['host'], $port, $projectId);

        return [$endpoint, (string) $parts['user']];
    }
}
