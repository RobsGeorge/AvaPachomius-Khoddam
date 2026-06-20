<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attendance timezone
    |--------------------------------------------------------------------------
    |
    | Used for "today" checks, manual close validation, and the midnight
    | auto-close scheduler.
    |
    */

    'timezone' => env('ATTENDANCE_TIMEZONE', 'Africa/Cairo'),

    /*
    |--------------------------------------------------------------------------
    | System user for automated attendance close
    |--------------------------------------------------------------------------
    |
    | user_id stored as taken_by_id on auto-generated absent records.
    |
    */

    'system_user_id' => env('ATTENDANCE_SYSTEM_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Auto-close at local midnight
    |--------------------------------------------------------------------------
    */

    'auto_close_enabled' => env('ATTENDANCE_AUTO_CLOSE', true),

    /*
    |--------------------------------------------------------------------------
    | Late attendance policy defaults (overridden by admin UI in attendance_policy)
    |--------------------------------------------------------------------------
    */

    'late_threshold_minutes' => (int) env('ATTENDANCE_LATE_THRESHOLD_MINUTES', 15),

    'late_grade_percentage' => (float) env('ATTENDANCE_LATE_GRADE_PERCENTAGE', 50),

    'default_session_start_time' => env('ATTENDANCE_DEFAULT_SESSION_START_TIME', '09:00:00'),

];
