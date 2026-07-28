<?php

return [

    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'EGP'),

    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'subscription_active_statuses' => [
        'trialing',
        'active',
        'past_due',
        'grace',
        'comped',
    ],

    'quota_period_keys' => [
        'storage_bytes' => 'lifetime',
        'max_active_users' => 'lifetime',
        'max_courses' => 'lifetime',
    ],

];
