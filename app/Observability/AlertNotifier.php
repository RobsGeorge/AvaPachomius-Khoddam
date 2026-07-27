<?php

namespace App\Observability;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertNotifier
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function maybeNotify(array $event): void
    {
        $url = config('observability.alert_webhook_url');
        if (! is_string($url) || $url === '') {
            return;
        }

        $severity = $event['severity'] ?? 'error';
        if (! in_array($severity, ['error', 'critical'], true)) {
            return;
        }

        try {
            Http::timeout(2)->post($url, [
                'text' => sprintf(
                    '[%s/%s] %s (fp=%s)',
                    $severity,
                    $event['category'] ?? 'unknown',
                    $event['message'] ?? '',
                    substr((string) ($event['fingerprint'] ?? ''), 0, 12)
                ),
                'severity' => $severity,
                'category' => $event['category'] ?? null,
                'fingerprint' => $event['fingerprint'] ?? null,
                'church_id' => $event['church_id'] ?? null,
                'request_id' => $event['request_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('observability.alert_webhook_failed', ['error' => $e->getMessage()]);
        }
    }
}
