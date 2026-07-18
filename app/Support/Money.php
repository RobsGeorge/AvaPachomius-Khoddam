<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-units money helpers (CLAUDE.md rule 7). Never uses PHP floats.
 * Decimal strings in / out; storage is bigint minor units + currency + fx_rate.
 */
final class Money
{
    public const DEFAULT_CURRENCY = 'EGP';

    /** Fixed-point identity rate as a decimal string (exact). */
    public const DEFAULT_FX_RATE = '1';

    public static function toMinor(string $decimalAmount, int $scale = 2): int
    {
        $normalized = self::normalizeDecimal($decimalAmount);
        $parts = explode('.', $normalized, 2);
        $whole = $parts[0];
        $frac = isset($parts[1]) ? substr(str_pad($parts[1], $scale, '0'), 0, $scale) : str_repeat('0', $scale);

        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = ltrim($whole, '+-');
        $whole = $whole === '' ? '0' : $whole;

        if (! ctype_digit($whole) || ! ctype_digit($frac)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $minor = ((int) $whole) * (10 ** $scale) + (int) $frac;

        return $sign * $minor;
    }

    public static function fromMinor(int $minor, int $scale = 2): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        $whole = intdiv($abs, 10 ** $scale);
        $frac = $abs % (10 ** $scale);

        return $sign.$whole.'.'.str_pad((string) $frac, $scale, '0', STR_PAD_LEFT);
    }

    public static function net(int $grossMinor, int $deductionsMinor): int
    {
        return $grossMinor - $deductionsMinor;
    }

    public static function assertNonNegative(int $minor, string $field = 'amount'): void
    {
        if ($minor < 0) {
            throw new InvalidArgumentException($field.' must be non-negative.');
        }
    }

    private static function normalizeDecimal(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        return $amount;
    }
}
