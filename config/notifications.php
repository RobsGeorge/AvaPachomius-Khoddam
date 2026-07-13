<?php

return [
    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],

    'categories' => [
        'announcements' => ['admin_announcement'],
        'academic' => [
            'assignment_deadline',
            'exam_upcoming',
            'session_upcoming',
            'grade_posted',
            'new_lecture_content',
            'assignment_needs_grading',
            'below_passing_grade',
            'attendance_absent_streak',
            'session_unclosed',
            'course_graduation_announced',
        ],
        'events' => [
            'event_nearby',
            'event_new_reservable',
            'event_reservation_cancelled',
        ],
        'social' => ['birthday_today'],
        'reminders' => ['custom_reminder'],
    ],

    'types' => [
        'admin_announcement' => [
            'label' => 'notifications.types.admin_announcement',
            'category' => 'announcements',
            'audience' => ['student', 'instructor', 'admin'],
            'mandatory' => true,
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'birthday_today' => [
            'label' => 'notifications.types.birthday_today',
            'category' => 'social',
            'audience' => ['student', 'instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'assignment_deadline' => [
            'label' => 'notifications.types.assignment_deadline',
            'category' => 'academic',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => ['lead_hours' => 24],
            ],
        ],
        'exam_upcoming' => [
            'label' => 'notifications.types.exam_upcoming',
            'category' => 'academic',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => ['lead_hours' => 24],
            ],
        ],
        'session_upcoming' => [
            'label' => 'notifications.types.session_upcoming',
            'category' => 'academic',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => ['lead_hours' => 24],
            ],
        ],
        'grade_posted' => [
            'label' => 'notifications.types.grade_posted',
            'category' => 'academic',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'new_lecture_content' => [
            'label' => 'notifications.types.new_lecture_content',
            'category' => 'academic',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'event_nearby' => [
            'label' => 'notifications.types.event_nearby',
            'category' => 'events',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => ['lead_days' => 7],
            ],
        ],
        'event_new_reservable' => [
            'label' => 'notifications.types.event_new_reservable',
            'category' => 'events',
            'audience' => ['student'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'attendance_absent_streak' => [
            'label' => 'notifications.types.attendance_absent_streak',
            'category' => 'academic',
            'audience' => ['instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => ['sessions_lookback' => 3],
            ],
        ],
        'session_unclosed' => [
            'label' => 'notifications.types.session_unclosed',
            'category' => 'academic',
            'audience' => ['instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => ['unclosed_days' => 7],
            ],
        ],
        'assignment_needs_grading' => [
            'label' => 'notifications.types.assignment_needs_grading',
            'category' => 'academic',
            'audience' => ['instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'event_reservation_cancelled' => [
            'label' => 'notifications.types.event_reservation_cancelled',
            'category' => 'events',
            'audience' => ['instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'below_passing_grade' => [
            'label' => 'notifications.types.below_passing_grade',
            'category' => 'academic',
            'audience' => ['instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'custom_reminder' => [
            'label' => 'notifications.types.custom_reminder',
            'category' => 'reminders',
            'audience' => ['student', 'instructor', 'admin'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'course_application_submitted' => [
            'label' => 'notifications.types.course_application_submitted',
            'category' => 'academic',
            'audience' => ['admin', 'instructor'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
        'course_graduation_announced' => [
            'label' => 'notifications.types.course_graduation_announced',
            'category' => 'academic',
            'audience' => ['admin', 'instructor'],
            'defaults' => [
                'portal_enabled' => true,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'config' => [],
            ],
        ],
    ],
];
