<?php

/*
|--------------------------------------------------------------------------
| Self-serve church signup (F-22 / S1)
|--------------------------------------------------------------------------
|
| When disabled (default), /register-church stays a lead queue: verify email
| then superadmin review. When enabled, verify provisions a trial tenant.
| Pack catalog /pricing / Guided Setup stay parked for later waves.
|
*/

return [

    'enabled' => (bool) env('SELF_SERVE_CHURCH_SIGNUP', false),

    'trial_days' => [
        'parish' => 30,
        'one_service' => 7,
    ],

    /*
    | D7 trial capability subsets (no pack SKUs in S1).
    | Core = curriculum, attendance, assignments, announcements.
    | Assessment = exams, grades, assessments, live_quiz, feedback.
    | Parish Ops = church_management, public_site.
    */
    'capabilities' => [
        'one_service' => [
            'curriculum',
            'attendance',
            'assignments',
            'announcements',
            'exams',
            'grades',
            'assessments',
            'live_quiz',
            'feedback',
        ],
        'parish' => [
            'curriculum',
            'attendance',
            'assignments',
            'announcements',
            'exams',
            'grades',
            'assessments',
            'live_quiz',
            'feedback',
            'church_management',
            'public_site',
        ],
    ],

    /*
    | D18: system hosts + a small famous-name deny list. Always merged with
    | tenancy.main_slug so Tenant Zero cannot be squatted.
    */
    'reserved_slugs' => [
        'www',
        'www2',
        'admin',
        'api',
        'app',
        'mail',
        'ftp',
        'cdn',
        'static',
        'assets',
        'status',
        'support',
        'help',
        'docs',
        'blog',
        'login',
        'register',
        'signup',
        'pricing',
        'billing',
        'staging',
        'test',
        'demo',
        'superadmin',
        'khedma',
        'deaconia',
        'avapakhomios',
        'coptic',
        'orthodox',
        'pope',
        'vatican',
        'church',
        'churches',
    ],

];
