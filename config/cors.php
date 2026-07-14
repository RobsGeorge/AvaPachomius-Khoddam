<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Mobile (Expo) and optional web clients. Prefer CORS_ALLOWED_ORIGINS
    | (comma-separated). Falls back to FRONTEND_URL for legacy local SPA work.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            env('FRONTEND_URL', 'http://localhost:3000').',http://localhost:8081,http://127.0.0.1:8081'
        ))
    ))),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https?://.*\.exp\.direct$#',
        '#^https?://.*\.exp\.host$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

];
