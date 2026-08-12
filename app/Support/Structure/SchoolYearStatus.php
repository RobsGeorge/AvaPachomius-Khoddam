<?php

namespace App\Support\Structure;

/**
 * Church school-year season statuses (T9c). Calendar signal only — never a global promote.
 */
final class SchoolYearStatus
{
    public const PLANNED = 'planned';

    public const ACTIVE = 'active';

    public const CLOSING = 'closing';

    public const CLOSED = 'closed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PLANNED, self::ACTIVE, self::CLOSING, self::CLOSED];
    }

    public static function isValid(?string $status): bool
    {
        return is_string($status) && in_array($status, self::all(), true);
    }

    public static function isOpenSeason(?string $status): bool
    {
        return in_array($status, [self::ACTIVE, self::CLOSING], true);
    }

    public static function canStartPromotion(?string $status): bool
    {
        return in_array($status, [self::PLANNED, self::ACTIVE], true);
    }
}
