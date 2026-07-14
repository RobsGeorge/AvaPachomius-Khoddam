<?php

namespace App\Tenancy;

use App\Models\Church;

/**
 * The current church (tenant) bound for the request. Read everywhere isolation is
 * enforced (the BelongsToChurch global scope, branding, nav). A church is bound by
 * ResolveTenant on normal requests when tenancy is enabled; tests bind one directly.
 *
 * When nothing is bound, `enforced()` is false and the global scope no-ops — which is
 * the single-institution behavior used in production until the T7 cutover.
 */
final class TenantContext
{
    private const KEY = 'currentChurch';

    public static function current(): ?Church
    {
        return app()->bound(self::KEY) ? app(self::KEY) : null;
    }

    public static function id(): ?int
    {
        return static::current()?->church_id;
    }

    public static function set(Church $church): void
    {
        app()->instance(self::KEY, $church);
    }

    public static function clear(): void
    {
        if (app()->bound(self::KEY)) {
            app()->forgetInstance(self::KEY);
        }
    }

    /** True when queries must be church-filtered (a church is bound). */
    public static function enforced(): bool
    {
        return static::current() !== null;
    }
}
