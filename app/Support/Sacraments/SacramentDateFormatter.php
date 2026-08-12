<?php

namespace App\Support\Sacraments;

use App\Models\Sacrament;
use Carbon\CarbonInterface;

/**
 * Renders sacrament dates honouring date_precision so year-only historical
 * records never imply a false day or month.
 */
final class SacramentDateFormatter
{
    public static function format(Sacrament $sacrament, ?string $locale = null): string
    {
        $date = $sacrament->date;
        if (! $date instanceof CarbonInterface) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();

        return match ($sacrament->date_precision) {
            Sacrament::PRECISION_YEAR => self::formatYear($date->year, $locale),
            Sacrament::PRECISION_MONTH => self::formatMonth($date->year, $date->month, $locale),
            default => self::formatDay($date, $locale),
        };
    }

    public static function formatYear(int $year, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return (string) trans('sacraments.date_year', ['year' => $year], $locale);
    }

    public static function formatMonth(int $year, int $month, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $padded = sprintf('%04d-%02d', $year, $month);

        return (string) trans('sacraments.date_month', ['ym' => $padded], $locale);
    }

    public static function formatDay(CarbonInterface $date, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return (string) trans('sacraments.date_day', ['ymd' => $date->format('Y-m-d')], $locale);
    }
}
