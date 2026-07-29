<?php

namespace App\Support\People;

final class PlacementMode
{
    public const INFO_ONLY = 'info_only';

    public const PORTAL_PENDING = 'portal_pending';

    public const PORTAL_ACTIVE = 'portal_active';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::INFO_ONLY, self::PORTAL_PENDING, self::PORTAL_ACTIVE];
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && in_array($value, self::all(), true);
    }
}
