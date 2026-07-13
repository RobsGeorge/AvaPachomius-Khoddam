<?php

return [
    'dashboard' => 'System Testing Report',
    'intro' => 'Automated test pipelines for the whole platform. Each pipeline runs in isolation; "Run all" runs every pipeline in sequence. The same pipelines gate every deployment in CI.',

    'run_pipelines' => 'Run pipelines',
    'run_all_sequence' => 'Run all (in sequence)',
    'confirm_run_all' => 'Run every test pipeline now? This may take a few minutes.',

    'latest_by_pipeline' => 'Latest result per pipeline',
    'never_run' => 'Never run',
    'history' => 'Run history',
    'no_history' => 'No test runs recorded yet.',
    'pipeline' => 'Pipeline',
    'results' => 'Results',
    'duration' => 'Duration',
    'triggered_by' => 'Triggered by',
    'view_output' => 'Output',
    'output_title' => 'Test run output',
    'back_to_report' => 'Back to report',
    'raw_output' => 'Raw output',

    // Pipeline names
    'suite_unit' => 'Unit',
    'suite_feature' => 'Feature',
    'suite_smoke' => 'Smoke (UI/endpoints)',
    'suite_api' => 'API',
    'suite_notifications' => 'Notifications',
    'suite_mail' => 'Mail & external',
    'suite_tenancy' => 'Tenant isolation',
    'suite_load' => 'Load',

    // Statuses
    'status_passed' => 'passed',
    'status_failed' => 'failed',
    'status_pending' => 'pending',

    // Summaries
    'all_passed' => 'All :total tests passed',
    'failed_summary' => ':failed failed, :passed passed',
    'skipped_n' => ':n skipped',
    'no_tests' => 'No tests executed',
    'empty_output' => 'No output was produced by the test runner.',

    // Flash / errors
    'run_completed_ok' => 'Test run completed — all pipelines passed.',
    'run_completed_with_failures' => 'Test run completed with failures. Review the report below.',
    'run_failed' => 'The test run could not be completed: :message',
    'table_missing' => 'The system_test_runs table is missing. Run migrations first.',
    'phpunit_missing' => 'PHPUnit is not installed (composer install with dev dependencies).',
    'autoload_missing' => 'The test autoloader is unavailable (run composer dump-autoload).',
    'sqlite_missing' => 'The pdo_sqlite PHP extension is required to run the test pipelines.',
];
