<?php

namespace Tests\Feature\Observability;

use App\Models\InfraSample;
use App\Models\ObservabilityEvent;
use App\Models\UsageRollup;
use App\Observability\AlertNotifier;
use App\Observability\Sinks\SentryErrorSink;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\EventModuleTestCase;

class ObservabilityRetentionAndSinksTest extends EventModuleTestCase
{
    public function test_prune_command_deletes_aged_rows(): void
    {
        config([
            'observability.retention.events_days' => 7,
            'observability.retention.infra_days' => 7,
            'observability.retention.rollups_days' => 7,
        ]);

        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now()->subDays(30),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'old'),
            'message' => 'old event',
        ]);
        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'new'),
            'message' => 'new event',
        ]);

        InfraSample::query()->create([
            'sampled_at' => now()->subDays(30),
            'host' => 'old-host',
            'source' => 'local_proc',
        ]);
        UsageRollup::withoutTenancy()->create([
            'bucket_start' => now()->subDays(30),
            'church_id' => 1,
            'request_count' => 1,
            'active_users' => 1,
            'unique_sessions' => 1,
        ]);

        Artisan::call('observability:prune');

        $this->assertFalse(
            ObservabilityEvent::withoutTenancy()->where('message', 'old event')->exists()
        );
        $this->assertTrue(
            ObservabilityEvent::withoutTenancy()->where('message', 'new event')->exists()
        );
        $this->assertSame(0, InfraSample::query()->count());
        $this->assertSame(0, UsageRollup::withoutTenancy()->count());
    }

    public function test_sentry_sink_posts_when_dsn_configured(): void
    {
        Http::fake();

        config([
            'observability.sentry_dsn' => 'https://publickey@sentry.example/123',
        ]);

        (new SentryErrorSink())->send([
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => 'abc',
            'message' => 'boom',
            'exception_class' => 'RuntimeException',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/123/store/'));
    }

    public function test_alert_notifier_posts_critical_events(): void
    {
        Http::fake();
        config(['observability.alert_webhook_url' => 'https://hooks.example/alert']);

        (new AlertNotifier())->maybeNotify([
            'severity' => 'critical',
            'category' => 'database',
            'message' => 'db down',
            'fingerprint' => str_repeat('a', 40),
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example/alert');
    }
}
