<?php

return [
    'blocked_commands' => [
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'tinker',
        'env',
        'down',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler health
    |--------------------------------------------------------------------------
    |
    | The heartbeat task ticks every minute when OS cron calls schedule:run.
    | The portal marks the scheduler unhealthy if no tick arrives within this
    | window (minutes).
    |
    */
    'health' => [
        'stale_after_minutes' => 5,
    ],

    'tasks' => [
        'scheduler.heartbeat' => [
            'label' => 'scheduled_tasks.tasks.scheduler_heartbeat',
            'description' => 'scheduled_tasks.scheduler_heartbeat_desc',
            'type' => 'callback',
            'callback' => [\App\Services\SchedulerHealthService::class, 'recordHeartbeat'],
            'schedule' => ['frequency' => 'every_minute'],
            'always_enabled' => true,
            'record_runs' => false,
        ],
        'attendance.mark_absent' => [
            'label' => 'scheduled_tasks.tasks.attendance_mark_absent',
            'description' => 'scheduled_tasks.attendance_mark_absent_desc',
            'type' => 'command',
            'command' => 'attendance:mark-absent',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '00:00',
                'timezone' => 'attendance.timezone',
            ],
            'when_config' => 'attendance.auto_close_enabled',
        ],
        'pending_registrations.purge' => [
            'label' => 'scheduled_tasks.tasks.pending_registrations_purge',
            'description' => 'scheduled_tasks.pending_registrations_purge_desc',
            'type' => 'callback',
            'callback' => [\App\Services\PendingRegistrationService::class, 'purgeStale'],
            'schedule' => ['frequency' => 'daily'],
        ],
        'birthdays.notify_monthly' => [
            'label' => 'scheduled_tasks.tasks.birthdays_notify_monthly',
            'description' => 'scheduled_tasks.birthdays_notify_monthly_desc',
            'type' => 'command',
            'command' => 'birthdays:notify-monthly',
            'schedule' => [
                'frequency' => 'monthly_on',
                'day' => 1,
                'time' => '08:00',
                'timezone' => 'attendance.timezone',
            ],
        ],
        'birthdays.notify_daily' => [
            'label' => 'scheduled_tasks.tasks.birthdays_notify_daily',
            'description' => 'scheduled_tasks.birthdays_notify_daily_desc',
            'type' => 'command',
            'command' => 'birthdays:notify-daily',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '00:05',
                'timezone' => 'attendance.timezone',
            ],
        ],
        'notifications.scan_deadlines' => [
            'label' => 'scheduled_tasks.tasks.notifications_scan_deadlines',
            'description' => 'scheduled_tasks.notifications_scan_deadlines_desc',
            'type' => 'command',
            'command' => 'notifications:scan-deadlines',
            'schedule' => ['frequency' => 'hourly'],
        ],
        'notifications.scan_events' => [
            'label' => 'scheduled_tasks.tasks.notifications_scan_events',
            'description' => 'scheduled_tasks.notifications_scan_events_desc',
            'type' => 'command',
            'command' => 'notifications:scan-events',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '07:00',
            ],
        ],
        'notifications.scan_instructor' => [
            'label' => 'scheduled_tasks.tasks.notifications_scan_instructor',
            'description' => 'scheduled_tasks.notifications_scan_instructor_desc',
            'type' => 'command',
            'command' => 'notifications:scan-instructor',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '08:00',
            ],
        ],
        'notifications.scan_grades_risk' => [
            'label' => 'scheduled_tasks.tasks.notifications_scan_grades_risk',
            'description' => 'scheduled_tasks.notifications_scan_grades_risk_desc',
            'type' => 'command',
            'command' => 'notifications:scan-grades-risk',
            'schedule' => [
                'frequency' => 'weekly_on',
                'day' => 1,
                'time' => '09:00',
            ],
        ],
        'notifications.fire_reminders' => [
            'label' => 'scheduled_tasks.tasks.notifications_fire_reminders',
            'description' => 'scheduled_tasks.notifications_fire_reminders_desc',
            'type' => 'command',
            'command' => 'notifications:fire-reminders',
            'schedule' => ['frequency' => 'every_five_minutes'],
        ],
        'photos.send_reupload_reminders' => [
            'label' => 'scheduled_tasks.tasks.photos_send_reupload_reminders',
            'description' => 'scheduled_tasks.photos_send_reupload_reminders_desc',
            'type' => 'command',
            'command' => 'photos:send-reupload-reminders',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '09:30',
                'timezone' => 'attendance.timezone',
            ],
        ],
        'audit.prune' => [
            'label' => 'scheduled_tasks.tasks.audit_prune',
            'description' => 'scheduled_tasks.audit_prune_desc',
            'type' => 'command',
            'command' => 'audit:prune',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '03:15',
            ],
        ],
        'observability.prune' => [
            'label' => 'scheduled_tasks.tasks.observability_prune',
            'description' => 'scheduled_tasks.observability_prune_desc',
            'type' => 'command',
            'command' => 'observability:prune',
            'schedule' => [
                'frequency' => 'daily_at',
                'time' => '03:30',
            ],
        ],
    ],
];
