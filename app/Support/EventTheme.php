<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Event theme ("Liturgical" mode) — a switchable sub-theme for special church events.
 *
 * Stored per-church in church.settings.event_theme. Active when EITHER a manual
 * override is on OR today falls within a scheduled period ("Both" behaviour).
 * Purely presentational: adds the {@see self::THEME_CLASS} body class, which the
 * stylesheet uses to swap the Sacred (teal) palette for the Liturgical (burgundy) one.
 */
final class EventTheme
{
    public const SETTINGS_KEY = 'event_theme';

    public const THEME_CLASS = 'event-liturgical';

    public const MAX_PERIODS = 20;

    /** @return array{enabled_manual: bool, periods: list<array{start: string, end: string, label: string}>} */
    public static function defaults(): array
    {
        return ['enabled_manual' => false, 'periods' => []];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{enabled_manual: bool, periods: list<array{start: string, end: string, label: string}>}
     */
    public static function fromSettings(?array $settings): array
    {
        $cfg = is_array($settings[self::SETTINGS_KEY] ?? null) ? $settings[self::SETTINGS_KEY] : [];

        return [
            'enabled_manual' => (bool) ($cfg['enabled_manual'] ?? false),
            'periods' => self::sanitizePeriods($cfg['periods'] ?? []),
        ];
    }

    /**
     * Is event mode active right now? Manual override OR any scheduled period covering "today".
     *
     * @param  array<string, mixed>  $config
     */
    public static function isActive(array $config, ?CarbonInterface $now = null): bool
    {
        if (! empty($config['enabled_manual'])) {
            return true;
        }

        $today = ($now ?? Carbon::now())->toDateString();

        foreach (($config['periods'] ?? []) as $period) {
            $start = $period['start'] ?? null;
            $end = $period['end'] ?? null;
            if ($start !== null && $end !== null && $today >= $start && $today <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{enabled_manual: bool, periods: list<array{start: string, end: string, label: string}>}
     */
    public static function normalizeInput(array $input): array
    {
        return [
            'enabled_manual' => filter_var($input['enabled_manual'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'periods' => self::sanitizePeriods($input['periods'] ?? []),
        ];
    }

    /**
     * @return list<array{start: string, end: string, label: string}>
     */
    private static function sanitizePeriods(mixed $periods): array
    {
        if (! is_array($periods)) {
            return [];
        }

        $out = [];
        foreach ($periods as $period) {
            if (! is_array($period)) {
                continue;
            }

            $start = self::normalizeDate($period['start'] ?? null);
            $end = self::normalizeDate($period['end'] ?? null);
            if ($start === null || $end === null || $end < $start) {
                continue;
            }

            $label = trim((string) ($period['label'] ?? ''));
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 80);
            }

            $out[] = ['start' => $start, 'end' => $end, 'label' => $label];
            if (count($out) >= self::MAX_PERIODS) {
                break;
            }
        }

        return $out;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
