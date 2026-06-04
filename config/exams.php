<?php

return [
    'autosave_interval_seconds' => (int) env('EXAM_AUTOSAVE_SECONDS', 30),

    /** Warnings before auto-termination (1 = one warning, second violation terminates). */
    'proctor_max_warnings' => (int) env('EXAM_PROCTOR_MAX_WARNINGS', 1),

    'proctor_debounce_seconds' => (int) env('EXAM_PROCTOR_DEBOUNCE_SECONDS', 5),

    'essay_grading_driver' => env('EXAM_ESSAY_DRIVER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url'=> env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
];
