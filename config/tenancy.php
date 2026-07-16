<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Church tenancy (multi-church platform — see docs/khedma-master-plan.md)
    |--------------------------------------------------------------------------
    |
    | Church == the tenant (isolation boundary). This is T0 (foundation): the
    | structures exist and existing data is backfilled into Church #1, but NOTHING
    | reads church_id yet — zero behavior change while MULTI_TENANT is off. The
    | global scope + request resolution arrive in T1.
    |
    */

    // Master switch. While false (production until the T7 cutover), ResolveTenant does
    // not bind a church, so the BelongsToChurch global scope no-ops and the app behaves
    // exactly as a single-institution system. Isolation is still fully exercised in tests
    // by binding a church context explicitly (TenantContext::set()).
    'enabled' => (bool) env('MULTI_TENANT', false),

    // Slug of the default church all pre-existing data belongs to (Tenant Zero).
    'main_slug' => env('TENANCY_MAIN_SLUG', 'main'),

    // Human name for that church. Never leave as the literal "Main" in production.
    'main_name' => env('TENANCY_MAIN_NAME', 'AvaPachomius'),

    // Host that serves the cross-church superadmin console (no church binding). Used from T4.
    'console_host' => env('TENANCY_CONSOLE_HOST', 'admin.localhost'),

    // Data-root tables that carry church_id and (from T1) the BelongsToChurch global scope.
    // Auth tables (roles, user_course_role, user_service_role, permissions) are intentionally
    // excluded until T3. This list is the single source of truth for the tenant boundary and
    // will grow as later phases bring more data roots under isolation.
    'tenant_tables' => [
        'course', 'modules', 'content', 'assignments',
        'session', 'exams', 'assessment', 'course_assessment',
        'attendance', 'grade_categories', 'activity_logs',
        'service',
    ],

];
