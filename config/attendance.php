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

];
