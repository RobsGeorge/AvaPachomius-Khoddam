<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Church tenancy (multi-church platform — see docs/khedma-master-plan.md)
    |--------------------------------------------------------------------------
    |
    | Church == the tenant (isolation boundary). This is T0/P1.1 (foundation):
    | the structures exist and existing data is backfilled into Organization #1 /
    | Church #1, but NOTHING enforces isolation while MULTI_TENANT is off. The
    | global scope + request resolution arrive in T1.
    |
    | `organizations` is the organizations-shaped registry (§4); `church` is the
    | church-native compatibility table. Tenant rows carry `church_id` FK →
    | `organizations.organization_id` (numerically aligned with Church #1).
    |
    */

    'enabled' => (bool) env('MULTI_TENANT', false),

    // Subdomain of Tenant Zero (AvaPachomius).
    'main_slug' => env('TENANCY_MAIN_SLUG', 'avapakhomios'),

    'main_name' => env('TENANCY_MAIN_NAME', 'كنيسة الأنبا باخوميوس'),

    'console_host' => env('TENANCY_CONSOLE_HOST', 'admin.localhost'),

    // Apex / cookie parent domain (without leading dot). Used to build {slug}.{base} URLs
    // (T4 ChurchHost). Falls back to parse_url(APP_URL).host when unset.
    'base_domain' => env('TENANCY_BASE_DOMAIN'),

    /*
    | Data-root tables that carry church_id (FK → organizations.organization_id).
    | Auth/platform tables (permissions, user) are excluded. Child rows reached
    | only through a scoped parent may omit church_id — audit direct queries.
    */
    'tenant_tables' => [
        // Academic core
        'course',
        'modules',
        'content',
        'assignments',
        'assignment_submission',
        'session',
        'exams',
        'assessment',
        'course_assessment',
        'user_assessment',
        'attendance',
        'attendance_policy',
        'grade_categories',
        'grade_items',
        'student_grades',
        'lectures',
        'lecture_materials',
        // Ops / audit
        'activity_logs',
        // Service layer
        'service',
        'user_service_role',
        'service_application_forms',
        'service_applications',
        // Comms / community
        'announcements',
        'events',
        'feedback_surveys',
        'live_quizzes',
        'communication_logs',
        // Applications / graduation
        'course_application_forms',
        'course_applications',
        'course_graduations',
        'course_certificates',
        // RBAC anchors (nullable for platform templates until T3-enforce)
        'roles',
        'user_course_role',
    ],

];
