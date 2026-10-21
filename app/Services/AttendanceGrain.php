<?php

namespace App\Services;

/**
 * How a meeting is rolled: one sheet for the session, or one sheet per lecture.
 */
final class AttendanceGrain
{
    public const SESSION = 'session';

    public const LECTURE = 'lecture';

    public static function normalize(mixed $grain): string
    {
        return $grain === self::LECTURE ? self::LECTURE : self::SESSION;
    }
}
