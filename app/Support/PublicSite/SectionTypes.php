<?php

namespace App\Support\PublicSite;

use Illuminate\Validation\Rule;

/**
 * T10c — curated homepage section catalog (no freeform HTML).
 */
final class SectionTypes
{
    public const HERO = 'hero';

    public const ABOUT = 'about';

    public const LITURGY_TIMES = 'liturgy_times';

    public const CLERGY = 'clergy';

    public const GALLERY = 'gallery';

    public const LOCATION = 'location';

    public const CONTACT = 'contact';

    public const CTA_PORTAL = 'cta_portal';

    public const QUOTE = 'quote';

    public const CUSTOM_CARDS = 'custom_cards';

    public const MAX_SECTIONS = 12;

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::HERO,
            self::ABOUT,
            self::LITURGY_TIMES,
            self::CLERGY,
            self::GALLERY,
            self::LOCATION,
            self::CONTACT,
            self::CTA_PORTAL,
            self::QUOTE,
            self::CUSTOM_CARDS,
        ];
    }

    public static function label(string $type): string
    {
        return __('public_site.section_'.$type);
    }

    /** @return array<string, mixed> */
    public static function defaults(string $type): array
    {
        return match ($type) {
            self::HERO => [
                'headline_ar' => '',
                'headline_en' => '',
                'sub_ar' => '',
                'sub_en' => '',
                'image_media_id' => null,
                'cta_label_ar' => '',
                'cta_label_en' => '',
                'cta_url' => '',
            ],
            self::ABOUT => [
                'title_ar' => '',
                'title_en' => '',
                'body_ar' => '',
                'body_en' => '',
                'image_media_id' => null,
            ],
            self::LITURGY_TIMES => [
                'use_profile' => true,
            ],
            self::CLERGY => [
                'pull_priests' => true,
                'cards' => [],
            ],
            self::GALLERY => [
                'media_ids' => [],
            ],
            self::LOCATION => [
                'use_profile' => true,
                'map_embed_url' => '',
            ],
            self::CONTACT => [
                'use_profile' => true,
            ],
            self::CTA_PORTAL => [
                'headline_ar' => '',
                'headline_en' => '',
                'sub_ar' => '',
                'sub_en' => '',
            ],
            self::QUOTE => [
                'text_ar' => '',
                'text_en' => '',
                'citation_ar' => '',
                'citation_en' => '',
            ],
            self::CUSTOM_CARDS => [
                'cards' => [],
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public static function validationRules(string $type): array
    {
        $text = ['nullable', 'string', 'max:2000'];
        $short = ['nullable', 'string', 'max:255'];
        $url = ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/.+/i'];

        return match ($type) {
            self::HERO => [
                'headline_ar' => $short,
                'headline_en' => $short,
                'sub_ar' => $text,
                'sub_en' => $text,
                'image_media_id' => ['nullable', 'integer'],
                'cta_label_ar' => $short,
                'cta_label_en' => $short,
                'cta_url' => $url,
            ],
            self::ABOUT => [
                'title_ar' => $short,
                'title_en' => $short,
                'body_ar' => $text,
                'body_en' => $text,
                'image_media_id' => ['nullable', 'integer'],
            ],
            self::LITURGY_TIMES => [
                'use_profile' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            ],
            self::CLERGY => [
                'pull_priests' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
                'cards' => ['nullable', 'array', 'max:12'],
                'cards.*.name_ar' => $short,
                'cards.*.name_en' => $short,
                'cards.*.title_ar' => $short,
                'cards.*.title_en' => $short,
            ],
            self::GALLERY => [
                'media_ids' => ['nullable', 'array', 'max:24'],
                'media_ids.*' => ['integer'],
            ],
            self::LOCATION => [
                'use_profile' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
                'map_embed_url' => $url,
            ],
            self::CONTACT => [
                'use_profile' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            ],
            self::CTA_PORTAL => [
                'headline_ar' => $short,
                'headline_en' => $short,
                'sub_ar' => $text,
                'sub_en' => $text,
            ],
            self::QUOTE => [
                'text_ar' => $text,
                'text_en' => $text,
                'citation_ar' => $short,
                'citation_en' => $short,
            ],
            self::CUSTOM_CARDS => [
                'cards' => ['nullable', 'array', 'min:2', 'max:6'],
                'cards.*.title_ar' => $short,
                'cards.*.title_en' => $short,
                'cards.*.body_ar' => $text,
                'cards.*.body_en' => $text,
            ],
            default => [],
        };
    }

    /** @param  array<string, mixed>  $input */
    public static function normalizeContent(string $type, array $input): array
    {
        $content = array_replace_recursive(self::defaults($type), $input);

        foreach ($content as $key => $value) {
            if (is_string($value) && ! str_ends_with($key, '_url') && $key !== 'map_embed_url') {
                $content[$key] = self::sanitizeText($value);
            }
        }

        if ($type === self::GALLERY) {
            $ids = $content['media_ids'] ?? [];
            $content['media_ids'] = array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
        }

        if (in_array($type, [self::CLERGY, self::CUSTOM_CARDS], true)) {
            $cards = $content['cards'] ?? [];
            if (! is_array($cards)) {
                $cards = [];
            }
            $content['cards'] = array_values(array_map(static function (array $card): array {
                foreach ($card as $k => $v) {
                    if (is_string($v)) {
                        $card[$k] = self::sanitizeText($v);
                    }
                }

                return $card;
            }, $cards));
        }

        if (isset($content['image_media_id'])) {
            $content['image_media_id'] = filled($content['image_media_id'])
                ? (int) $content['image_media_id']
                : null;
        }

        foreach (['use_profile', 'pull_priests'] as $boolKey) {
            if (array_key_exists($boolKey, $content)) {
                $content[$boolKey] = filter_var($content[$boolKey], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $content;
    }

    public static function sanitizeText(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }

    public static function localized(array $content, string $fieldBase): ?string
    {
        $locale = app()->getLocale();
        $primary = $locale === 'ar' ? ($content[$fieldBase.'_ar'] ?? '') : ($content[$fieldBase.'_en'] ?? '');
        $fallback = $locale === 'ar' ? ($content[$fieldBase.'_en'] ?? '') : ($content[$fieldBase.'_ar'] ?? '');
        $text = filled($primary) ? $primary : $fallback;

        return filled($text) ? $text : null;
    }
}
