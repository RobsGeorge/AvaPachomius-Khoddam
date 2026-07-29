<?php

namespace App\Support\People;

final class PortalAccountPreference
{
    public const PREFER_PORTAL = 'prefer_portal';

    public const PREFER_INFO_ONLY = 'prefer_info_only';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PREFER_PORTAL, self::PREFER_INFO_ONLY];
    }

    public static function isValid(?string $value): bool
    {
        return $value === null || in_array($value, self::all(), true);
    }

    public static function defaultsInvite(?string $preference): bool
    {
        return $preference === self::PREFER_PORTAL;
    }
}
