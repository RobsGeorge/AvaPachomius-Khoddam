<?php

namespace App\Tenancy;

use App\Models\Church;

/**
 * Current church (tenant) for the request. Registered as a container singleton;
 * static helpers remain for call-site convenience.
 *
 * ResolveTenant binds this on every request:
 * - MULTI_TENANT=false → Tenant Zero (church 1)
 * - MULTI_TENANT=true  → subdomain / API token claim
 */
final class TenantContext
{
    private const KEY = 'currentChurch';

    private ?Church $church = null;

    public function churchId(): ?int
    {
        return $this->church?->church_id ?? static::id();
    }

    public function church(): ?Church
    {
        return $this->church ?? static::current();
    }

    public function bind(?Church $church): void
    {
        $this->church = $church;
        if ($church) {
            app()->instance(self::KEY, $church);
        } elseif (app()->bound(self::KEY)) {
            app()->forgetInstance(self::KEY);
        }
    }

    public static function current(): ?Church
    {
        if (app()->bound(self::class)) {
            $instance = app(self::class);
            if ($instance->church !== null) {
                return $instance->church;
            }
        }

        return app()->bound(self::KEY) ? app(self::KEY) : null;
    }

    public static function id(): ?int
    {
        return static::current()?->church_id;
    }

    public static function set(Church $church): void
    {
        if (app()->bound(self::class)) {
            app(self::class)->bind($church);
        } else {
            app()->instance(self::KEY, $church);
        }
    }

    public static function clear(): void
    {
        if (app()->bound(self::class)) {
            app(self::class)->bind(null);
        } elseif (app()->bound(self::KEY)) {
            app()->forgetInstance(self::KEY);
        }
    }

    /** True when queries must be church-filtered (a church is bound). */
    public static function enforced(): bool
    {
        return static::current() !== null;
    }
}
