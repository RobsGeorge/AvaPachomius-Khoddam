<?php

namespace App\Support;

/**
 * International place helpers for church identity (street → district → region → governorate → country).
 * Uniqueness key uses name + country + governorate + district (not street/region).
 */
final class ChurchPlace
{
    public const SHORT_NAME_MAX = 40;

    public const NAME_MAX = 120;

    public const SLUG_MAX = 40;

    /**
     * @param  array{
     *     name?: ?string,
     *     place_country_code?: ?string,
     *     place_governorate?: ?string,
     *     place_district?: ?string,
     * }  $parts
     */
    public static function placeKey(array $parts): ?string
    {
        $name = ArabicNameNormalizer::normalize($parts['name'] ?? null);
        $country = strtoupper(trim((string) ($parts['place_country_code'] ?? '')));
        $governorate = ArabicNameNormalizer::normalize($parts['place_governorate'] ?? null);
        $district = ArabicNameNormalizer::normalize($parts['place_district'] ?? null);

        if ($name === '' || $country === '' || ($governorate === '' && $district === '')) {
            return null;
        }

        $raw = implode('|', [$name, $country, $governorate, $district]);

        // Keep under 191 for unique index; hash when long (Arabic names).
        if (strlen($raw) <= 191) {
            return $raw;
        }

        return hash('sha256', $raw);
    }

    /**
     * Preferred chrome label (nav): short_name, else truncated name.
     */
    public static function shortName(?string $shortName, ?string $name): string
    {
        $short = trim((string) $shortName);
        if ($short !== '') {
            return mb_substr($short, 0, self::SHORT_NAME_MAX);
        }

        return mb_substr(trim((string) $name), 0, self::SHORT_NAME_MAX);
    }

    /**
     * Disambiguated admin/list label: short name + place suffix.
     *
     * @param  array{
     *     short_name?: ?string,
     *     name?: ?string,
     *     place_district?: ?string,
     *     place_governorate?: ?string,
     *     place_country_code?: ?string,
     * }  $church
     */
    public static function shownName(array $church): string
    {
        $base = self::shortName($church['short_name'] ?? null, $church['name'] ?? null);
        $bits = array_values(array_filter([
            trim((string) ($church['place_district'] ?? '')),
            trim((string) ($church['place_governorate'] ?? '')),
            strtoupper(trim((string) ($church['place_country_code'] ?? ''))),
        ], fn ($v) => $v !== ''));

        if ($bits === []) {
            return $base;
        }

        return $base.' — '.implode(', ', $bits);
    }

    /**
     * Map identity place into settings.public address/city for T10a CMS continuity.
     *
     * @param  array<string, mixed>  $settings
     * @param  array{
     *     place_street?: ?string,
     *     place_district?: ?string,
     *     place_region?: ?string,
     *     place_governorate?: ?string,
     *     place_country_code?: ?string,
     * }  $place
     * @return array<string, mixed>
     */
    public static function syncIntoPublicSettings(array $settings, array $place): array
    {
        $public = is_array($settings['public'] ?? null) ? $settings['public'] : [];

        $line = array_values(array_filter([
            trim((string) ($place['place_street'] ?? '')),
            trim((string) ($place['place_district'] ?? '')),
            trim((string) ($place['place_region'] ?? '')),
            trim((string) ($place['place_governorate'] ?? '')),
            strtoupper(trim((string) ($place['place_country_code'] ?? ''))),
        ], fn ($v) => $v !== ''));

        if ($line !== []) {
            $public['address'] = implode(', ', $line);
        }

        $city = trim((string) ($place['place_district'] ?? ''));
        if ($city === '') {
            $city = trim((string) ($place['place_governorate'] ?? ''));
        }
        if ($city !== '') {
            $public['city'] = $city;
        }

        $settings['public'] = $public;

        return $settings;
    }
}
