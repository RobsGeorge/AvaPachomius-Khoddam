<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo mode (main site: https://yourdomain.com/demo)
    |--------------------------------------------------------------------------
    |
    | When enabled, /demo and /demo/enter/{role} are available on the same app
    | as production. Demo rows are tagged is_demo=true in the shared database;
    | demo:reset only removes tagged data — real users are untouched.
    |
    */

    'enabled' => (bool) env('DEMO_ENABLED', false),

    /** When true, /register is disabled (optional; default false on main site). */
    'block_registration' => (bool) env('DEMO_BLOCK_REGISTRATION', false),

    'password' => env('DEMO_PASSWORD', 'Demo2026!'),

    'users' => [
        'student' => [
            'email' => env('DEMO_STUDENT_EMAIL', 'demo.student@demo.local'),
        ],
        'instructor' => [
            'email' => env('DEMO_INSTRUCTOR_EMAIL', 'demo.instructor@demo.local'),
        ],
        'admin' => [
            'email' => env('DEMO_ADMIN_EMAIL', 'demo.admin@demo.local'),
        ],
    ],

    'course_title' => 'Servants Prep (Demo)',

];
