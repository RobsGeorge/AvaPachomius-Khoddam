<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This is a server-rendered (Blade) application, so CORS only needs to
    | apply to the API surface — not every path. Allowed origins are driven
    | entirely by the CORS_ALLOWED_ORIGINS env var (comma-separated) and
    | default to *none*, so no origin is ever silently allowed in production.
    | (F-16 — previously leaked a hardcoded http://localhost:3000 default.)
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

];
