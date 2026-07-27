<?php

namespace App\Observability;

use App\Http\Middleware\AssignRequestId;
use App\Models\ObservabilityEvent;
use App\Observability\Contracts\ErrorSink;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Central write API for ops events. Persists to observability_events when available.
 */
class ObservabilityRecorder
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_token',
        '_token',
        'authorization',
        'cookie',
    ];

    public function __construct(
        private readonly ErrorSink $errorSink,
        private readonly ?AlertNotifier $alerts = null,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('observability.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $category,
        string $severity,
        string $message,
        array $context = [],
        ?Throwable $exception = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $safeContext = $this->redact($context);
        $fingerprint = $this->fingerprint($category, $message, $exception);
        $request = function_exists('request') ? request() : null;

        $requestId = $safeContext['request_id']
            ?? ($request?->attributes->get(AssignRequestId::ATTRIBUTE))
            ?? null;

        $userId = $safeContext['user_id']
            ?? Auth::id()
            ?? null;

        $churchId = $safeContext['church_id']
            ?? TenantContext::id()
            ?? null;

        $payload = [
            'severity' => $severity,
            'category' => $category,
            'fingerprint' => $fingerprint,
            'message' => Str::limit($message, 2000, ''),
            'exception_class' => $exception ? $exception::class : ($safeContext['exception_class'] ?? null),
            'stack_excerpt' => $exception ? $this->stackExcerpt($exception) : ($safeContext['stack_excerpt'] ?? null),
            'context' => $safeContext,
            'request_id' => $requestId,
            'user_id' => $userId !== null ? (int) $userId : null,
            'church_id' => $churchId !== null ? (int) $churchId : null,
            'http_status' => isset($safeContext['http_status']) ? (int) $safeContext['http_status'] : null,
            'url' => isset($safeContext['url']) ? Str::limit((string) $safeContext['url'], 2000, '') : ($request?->fullUrl()),
            'method' => $safeContext['method'] ?? $request?->method(),
            'route_name' => $safeContext['route_name'] ?? $request?->route()?->getName(),
            'service_id' => isset($safeContext['service_id']) ? (int) $safeContext['service_id'] : null,
            'session_id' => $safeContext['session_id'] ?? ($request?->hasSession() ? hash('sha256', (string) $request->session()->getId()) : null),
        ];

        $this->persist($payload);

        try {
            $this->errorSink->send($payload);
        } catch (Throwable $e) {
            Log::warning('observability.sink_failed', [
                'error' => $e->getMessage(),
                'fingerprint' => $fingerprint,
            ]);
        }

        try {
            $this->alerts?->maybeNotify($payload);
        } catch (Throwable $e) {
            Log::warning('observability.alert_failed', [
                'error' => $e->getMessage(),
                'fingerprint' => $fingerprint,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function exception(Throwable $e, array $context = []): void
    {
        $this->record(
            $this->categoryForException($e),
            $this->severityForException($e),
            $e->getMessage() ?: $e::class,
            $context,
            $e
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $keyStr = (string) $key;
            if ($this->isSensitiveKey($keyStr)) {
                $out[$keyStr] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$keyStr] = $this->redact($value);

                continue;
            }
            if (is_object($value)) {
                continue;
            }
            $out[$keyStr] = $value;
        }

        return $out;
    }

    public function fingerprint(string $category, string $message, ?Throwable $exception = null): string
    {
        $normalized = preg_replace('/\d+/', 'N', $message) ?? $message;
        $normalized = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', 'UUID', $normalized) ?? $normalized;
        $normalized = Str::lower(trim($normalized));

        $parts = [
            $category,
            $exception ? $exception::class : '',
            $normalized,
        ];

        if ($exception !== null) {
            $parts[] = basename($exception->getFile()).':'.$exception->getLine();
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persist(array $payload): void
    {
        try {
            if (! Schema::hasTable('observability_events')) {
                return;
            }

            ObservabilityEvent::withoutTenancy()->create([
                'occurred_at' => now(),
                'severity' => $payload['severity'],
                'category' => $payload['category'],
                'fingerprint' => $payload['fingerprint'],
                'message' => $payload['message'],
                'exception_class' => $payload['exception_class'],
                'stack_excerpt' => $payload['stack_excerpt'],
                'http_status' => $payload['http_status'],
                'url' => $payload['url'],
                'method' => $payload['method'],
                'route_name' => $payload['route_name'],
                'user_id' => $payload['user_id'],
                'church_id' => $payload['church_id'],
                'service_id' => $payload['service_id'],
                'session_id' => $payload['session_id'],
                'request_id' => $payload['request_id'],
                'context' => $payload['context'],
            ]);
        } catch (Throwable $e) {
            Log::warning('observability.persist_failed', [
                'error' => $e->getMessage(),
                'fingerprint' => $payload['fingerprint'] ?? null,
            ]);
        }
    }

    private function stackExcerpt(Throwable $e): string
    {
        return Str::limit($e->getTraceAsString(), 4000, "\n…");
    }

    private function categoryForException(Throwable $e): string
    {
        $class = $e::class;
        if (str_contains($class, 'QueryException') || str_contains($class, 'PDOException')) {
            return 'database';
        }

        return 'exception';
    }

    private function severityForException(Throwable $e): string
    {
        $class = $e::class;
        if (str_contains($class, 'QueryException') || str_contains($class, 'PDOException')) {
            return 'critical';
        }

        return 'error';
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = Str::lower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($lower === $sensitive || str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
