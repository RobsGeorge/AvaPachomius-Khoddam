<?php

namespace App\Support\Pastoral;

use App\Models\Church;

/**
 * Church-configurable booking lead/cutoff + timezone defaults (PAC1).
 * Stored under church.settings; no dedicated columns.
 */
final class BookingRules
{
    public const DEFAULT_TIMEZONE = 'Africa/Cairo';

    public const DEFAULT_MIN_LEAD_MINUTES = 60;

    public const DEFAULT_CANCEL_CUTOFF_MINUTES = 120;

    public static function for(Church $church): self
    {
        $settings = is_array($church->settings) ? $church->settings : [];

        return new self(
            timezone: (string) ($settings['timezone'] ?? self::DEFAULT_TIMEZONE),
            minLeadMinutes: (int) data_get($settings, 'booking.min_lead_minutes', self::DEFAULT_MIN_LEAD_MINUTES),
            cancelCutoffMinutes: (int) data_get($settings, 'booking.cancel_cutoff_minutes', self::DEFAULT_CANCEL_CUTOFF_MINUTES),
        );
    }

    public function __construct(
        public readonly string $timezone,
        public readonly int $minLeadMinutes,
        public readonly int $cancelCutoffMinutes,
    ) {}
}
