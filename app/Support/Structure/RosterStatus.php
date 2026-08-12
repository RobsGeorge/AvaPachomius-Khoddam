<?php

namespace App\Support\Structure;

/**
 * Per-service roster / enrollment participation status (T9a).
 * Only {@see self::ACTIVE} is eligible for End-of-Cycle auto-propose.
 */
final class RosterStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const LEFT = 'left';

    public const PASTORAL_HOLD = 'pastoral_hold';

    /** Legacy enrollment mirror of archived UCR. */
    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::LEFT,
            self::PASTORAL_HOLD,
            self::ARCHIVED,
        ];
    }

    public static function isValid(?string $status): bool
    {
        return is_string($status) && in_array($status, self::all(), true);
    }

    public static function isEligibleForPropose(?string $status): bool
    {
        return ($status ?? self::ACTIVE) === self::ACTIVE;
    }
}
