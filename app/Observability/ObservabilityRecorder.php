<?php

namespace App\Observability;

use App\Observability\Contracts\ErrorSink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Central write API for ops events. W0: sink-only (no DB). Later waves persist.
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

        $payload = [
            'severity' => $severity,
            'category' => $category,
            'fingerprint' => $fingerprint,
            'message' => Str::limit($message, 2000, ''),
            'exception_class' => $exception ? $exception::class : ($safeContext['exception_class'] ?? null),
            'stack_excerpt' => $exception ? $this->stackExcerpt($exception) : null,
            'context' => $safeContext,
            'request_id' => $safeContext['request_id'] ?? null,
            'user_id' => isset($safeContext['user_id']) ? (int) $safeContext['user_id'] : null,
            'church_id' => isset($safeContext['church_id']) ? (int) $safeContext['church_id'] : null,
        ];

        try {
            $this->errorSink->send($payload);
        } catch (Throwable $e) {
            Log::warning('observability.sink_failed', [
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
        $category = $this->categoryForException($e);
        $severity = $this->severityForException($e);

        $this->record($category, $severity, $e->getMessage() ?: $e::class, $context, $e);
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
            $file = $exception->getFile();
            $line = $exception->getLine();
            $parts[] = basename($file).':'.$line;
        }

        return hash('sha256', implode('|', $parts));
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
