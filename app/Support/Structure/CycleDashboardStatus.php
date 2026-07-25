<?php

namespace App\Support\Structure;

/**
 * Per-service row status on the Church Cycle Dashboard (T9c).
 * No one-button church-wide upgrade — statuses drive links into per-service wizards.
 */
final class CycleDashboardStatus
{
    public const READY = 'ready';

    public const BLOCKED = 'blocked';

    public const DONE = 'done';

    public const SKIPPED = 'skipped';

    public const COURSE_CLOSE = 'course_close_only';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::READY,
            self::BLOCKED,
            self::DONE,
            self::SKIPPED,
            self::COURSE_CLOSE,
        ];
    }
}
