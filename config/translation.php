<?php

return [
    'supported_locales' => ['ar', 'en'],

    'locale_labels' => [
        'ar' => 'العربية',
        'en' => 'English',
    ],

    'rtl_locales' => ['ar'],

    /*
    | mymemory — free tier, no API key (good for admin assist)
    | google  — requires GOOGLE_TRANSLATE_KEY in .env
    */
    'auto_translate_driver' => env('TRANSLATE_DRIVER', 'mymemory'),

    'google_api_key' => env('GOOGLE_TRANSLATE_KEY'),
];
