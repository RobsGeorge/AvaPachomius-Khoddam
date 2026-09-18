<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit log retention (days)
    |--------------------------------------------------------------------------
    |
    | activity_logs and login_trials grow continuously. The audit:prune command
    | deletes rows older than these windows. access_ledger is intentionally
    | excluded (tamper-evident; separate compliance decision).
    |
    */

    'retention' => [
        'activity_logs_days' => (int) env('AUDIT_ACTIVITY_RETENTION_DAYS', 90),
        'login_trials_days' => (int) env('AUDIT_LOGIN_TRIALS_RETENTION_DAYS', 90),
    ],

];
