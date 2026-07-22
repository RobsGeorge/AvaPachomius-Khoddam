<?php

namespace App\Support;

/**
 * Arabic (and Latin) name normalizer for people.normalized_name and duplicate detection.
 *
 * Rules (documented + covered by unit tests):
 * 1. Trim and collapse internal whitespace to a single space.
 * 2. Remove tatweel (ـ U+0640).
 * 3. Strip Arabic diacritics (tashkeel): fatḥa, ḍamma, kasra, sukūn, shadda, tanwīn, dagger alef, etc.
 * 4. Unify alef variants: أ إ آ ٱ → ا
 * 5. Unify tāʾ marbūṭa: ة → ه
 * 6. Unify alif maqṣūra: ى → ي
 * 7. Lowercase Latin letters (mb_strtolower UTF-8).
 *
 * Example: "هالة" and "هاله" both normalize to "هاله".
 */
final class ArabicNameNormalizer
{
    /** Arabic combining marks / diacritics to strip. */
    private const DIACRITICS = [
        "\u{064B}", // tanwīn fatḥ
        "\u{064C}", // tanwīn ḍamm
        "\u{064D}", // tanwīn kasr
        "\u{064E}", // fatḥa
        "\u{064F}", // ḍamma
        "\u{0650}", // kasra
        "\u{0651}", // shadda
        "\u{0652}", // sukūn
        "\u{0653}", // madda above
        "\u{0654}", // hamza above
        "\u{0655}", // hamza below
        "\u{0670}", // dagger alef
        "\u{0640}", // tatweel (also stripped explicitly)
    ];

    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $value = trim($name);
        if ($value === '') {
            return '';
        }

        $value = str_replace("\u{0640}", '', $value); // tatweel

        foreach (self::DIACRITICS as $mark) {
            $value = str_replace($mark, '', $value);
        }

        $value = str_replace(
            ["\u{0623}", "\u{0625}", "\u{0622}", "\u{0671}"], // أ إ آ ٱ
            "\u{0627}", // ا
            $value
        );

        $value = str_replace("\u{0629}", "\u{0647}", $value); // ة → ه
        $value = str_replace("\u{0649}", "\u{064A}", $value); // ى → ي

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');

        return trim($value);
    }

    /** Build a display full name then normalize. */
    public static function fromParts(?string $first, ?string $second = null, ?string $third = null): string
    {
        $full = trim(implode(' ', array_filter([
            trim((string) $first),
            trim((string) $second),
            trim((string) $third),
        ], fn ($p) => $p !== '')));

        return self::normalize($full);
    }
}
