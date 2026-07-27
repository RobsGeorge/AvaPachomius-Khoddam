<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Observability master switch
    |--------------------------------------------------------------------------
    |
    | When false, the recorder becomes a no-op (still safe to call). Keep true
    | on staging/production once waves land; local may disable to quiet noise.
    |
    */

    'enabled' => (bool) env('OBSERVABILITY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Error sink
    |--------------------------------------------------------------------------
    |
    | null  — discard after optional DB persist (wired in later waves)
    | log   — also write structured lines to the observability log channel
    | sentry — Sentry/GlitchTip-compatible HTTP sink (W6)
    |
    */

    'error_sink' => env('OBSERVABILITY_ERROR_SINK', 'log'),

    'sentry_dsn' => env('SENTRY_DSN', env('OBSERVABILITY_SENTRY_DSN')),

    /*
    |--------------------------------------------------------------------------
    | Infra metrics adapter
    |--------------------------------------------------------------------------
    |
    | null       — no samples
    | local_proc — Linux /proc + disk (default on VPS; W4)
    |
    */

    'infra_adapter' => env('OBSERVABILITY_INFRA_ADAPTER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Retention (days) — prune job in W6
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'events_days' => (int) env('OBSERVABILITY_EVENTS_RETENTION_DAYS', 90),
        'infra_days' => (int) env('OBSERVABILITY_INFRA_RETENTION_DAYS', 90),
        'rollups_days' => (int) env('OBSERVABILITY_ROLLUPS_RETENTION_DAYS', 730),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage rollup bucket size (minutes)
    |--------------------------------------------------------------------------
    */

    'usage_bucket_minutes' => (int) env('OBSERVABILITY_USAGE_BUCKET_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Client error beacon
    |--------------------------------------------------------------------------
    */

    'client_beacon' => [
        'enabled' => (bool) env('OBSERVABILITY_CLIENT_BEACON', true),
        'max_message_length' => 2000,
        'throttle_per_minute' => (int) env('OBSERVABILITY_CLIENT_THROTTLE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert webhook (W6) — optional
    |--------------------------------------------------------------------------
    */

    'alert_webhook_url' => env('OBSERVABILITY_ALERT_WEBHOOK_URL'),

];
