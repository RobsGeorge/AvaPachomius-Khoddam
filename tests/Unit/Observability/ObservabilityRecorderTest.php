<?php

namespace Tests\Unit\Observability;

use App\Observability\Adapters\NullInfraMetricsAdapter;
use App\Observability\Contracts\ErrorSink;
use App\Observability\Contracts\InfraMetricsAdapter;
use App\Observability\ObservabilityRecorder;
use App\Observability\Sinks\NullErrorSink;
use RuntimeException;
use Tests\TestCase;

class ObservabilityRecorderTest extends TestCase
{
    public function test_container_binds_recorder_and_contracts(): void
    {
        $this->assertInstanceOf(ObservabilityRecorder::class, app(ObservabilityRecorder::class));
        $this->assertInstanceOf(ErrorSink::class, app(ErrorSink::class));
        $this->assertInstanceOf(InfraMetricsAdapter::class, app(InfraMetricsAdapter::class));
    }

    public function test_redacts_sensitive_context_keys(): void
    {
        $recorder = new ObservabilityRecorder(new NullErrorSink());

        $redacted = $recorder->redact([
            'password' => 'secret',
            'user_id' => 42,
            'nested' => ['api_token' => 'abc', 'ok' => true],
        ]);

        $this->assertSame('[redacted]', $redacted['password']);
        $this->assertSame(42, $redacted['user_id']);
        $this->assertSame('[redacted]', $redacted['nested']['api_token']);
        $this->assertTrue($redacted['nested']['ok']);
    }

    public function test_fingerprint_stable_for_same_exception_shape(): void
    {
        $recorder = new ObservabilityRecorder(new NullErrorSink());

        $this->assertSame(
            $recorder->fingerprint('exception', 'User 12 failed'),
            $recorder->fingerprint('exception', 'User 99 failed')
        );

        $exception = new RuntimeException('User 12 failed');
        $this->assertSame(
            $recorder->fingerprint('exception', 'User 12 failed', $exception),
            $recorder->fingerprint('exception', 'User 99 failed', $exception)
        );
    }

    public function test_disabled_recorder_is_noop(): void
    {
        config(['observability.enabled' => false]);

        $sink = new class implements ErrorSink {
            public bool $called = false;

            public function send(array $event): void
            {
                $this->called = true;
            }
        };

        $recorder = new ObservabilityRecorder($sink);
        $recorder->record('auth', 'warning', 'login failed');

        $this->assertFalse($sink->called);
    }

    public function test_null_infra_adapter_returns_null(): void
    {
        $this->assertNull((new NullInfraMetricsAdapter())->sample());
    }
}
