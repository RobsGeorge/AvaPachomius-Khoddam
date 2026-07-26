<?php

namespace App\Support\PublicSite;

/**
 * T10a — helpers for church.settings.public (no new table).
 */
final class ChurchPublicProfile
{
    public const SETTINGS_KEY = 'public';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'tagline' => ['ar' => '', 'en' => ''],
            'about' => ['ar' => '', 'en' => ''],
            'address' => '',
            'city' => '',
            'geo' => ['lat' => null, 'lng' => null],
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'social' => [
                'facebook' => '',
                'youtube' => '',
                'instagram' => '',
            ],
            'liturgy_hours' => [],
            'show_on_public_site' => [
                'tagline' => true,
                'about' => true,
                'address' => true,
                'contact' => true,
                'social' => true,
                'liturgy_hours' => true,
            ],
        ];
    }

    /** @param  array<string, mixed>|null  $settings */
    public static function fromSettings(?array $settings): array
    {
        $public = is_array($settings[self::SETTINGS_KEY] ?? null)
            ? $settings[self::SETTINGS_KEY]
            : [];

        return array_replace_recursive(self::defaults(), $public);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input): array
    {
        $base = self::defaults();

        $base['tagline']['ar'] = trim((string) data_get($input, 'tagline.ar', ''));
        $base['tagline']['en'] = trim((string) data_get($input, 'tagline.en', ''));
        $base['about']['ar'] = trim((string) data_get($input, 'about.ar', ''));
        $base['about']['en'] = trim((string) data_get($input, 'about.en', ''));
        $base['address'] = trim((string) ($input['address'] ?? ''));
        $base['city'] = trim((string) ($input['city'] ?? ''));

        $lat = $input['geo']['lat'] ?? null;
        $lng = $input['geo']['lng'] ?? null;
        $base['geo']['lat'] = ($lat === '' || $lat === null) ? null : (float) $lat;
        $base['geo']['lng'] = ($lng === '' || $lng === null) ? null : (float) $lng;

        $base['phone'] = trim((string) ($input['phone'] ?? ''));
        $base['whatsapp'] = trim((string) ($input['whatsapp'] ?? ''));
        $base['email'] = trim((string) ($input['email'] ?? ''));

        foreach (['facebook', 'youtube', 'instagram'] as $network) {
            $base['social'][$network] = trim((string) data_get($input, "social.{$network}", ''));
        }

        $hours = [];
        foreach ((array) ($input['liturgy_hours'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $day = trim((string) ($row['day'] ?? ''));
            $timeAr = trim((string) data_get($row, 'time.ar', data_get($row, 'time_ar', '')));
            $timeEn = trim((string) data_get($row, 'time.en', data_get($row, 'time_en', '')));
            if ($day === '' && $timeAr === '' && $timeEn === '') {
                continue;
            }
            $hours[] = [
                'day' => $day,
                'time' => ['ar' => $timeAr, 'en' => $timeEn],
            ];
        }
        $base['liturgy_hours'] = $hours;

        foreach (array_keys($base['show_on_public_site']) as $group) {
            $base['show_on_public_site'][$group] = filter_var(
                data_get($input, "show_on_public_site.{$group}", true),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        return $base;
    }

    /** Localized string from ar/en pair. */
    public static function localized(array $pair, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $primary = $locale === 'en' ? 'en' : 'ar';
        $secondary = $primary === 'ar' ? 'en' : 'ar';
        $value = trim((string) ($pair[$primary] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return trim((string) ($pair[$secondary] ?? ''));
    }
}
