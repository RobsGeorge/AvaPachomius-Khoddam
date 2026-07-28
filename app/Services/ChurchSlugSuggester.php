<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Organization;
use App\Support\ChurchPlace;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Suggest globally unique kebab slugs from short_name (+ place disambiguators).
 */
class ChurchSlugSuggester
{
    /**
     * @param  array{
     *     short_name?: ?string,
     *     name?: ?string,
     *     place_country_code?: ?string,
     *     place_governorate?: ?string,
     *     place_district?: ?string,
     * }  $input
     * @return list<string>
     */
    public function suggest(array $input, int $limit = 5): array
    {
        $baseSource = trim((string) ($input['short_name'] ?? '')) !== ''
            ? (string) $input['short_name']
            : (string) ($input['name'] ?? '');

        $base = $this->toSlug($baseSource);
        if ($base === '') {
            $base = 'church';
        }

        $suffixes = [''];
        $country = strtolower(trim((string) ($input['place_country_code'] ?? '')));
        if ($country !== '') {
            $suffixes[] = $country;
        }
        $gov = $this->toSlug((string) ($input['place_governorate'] ?? ''));
        if ($gov !== '') {
            $suffixes[] = $gov;
        }
        $district = $this->toSlug((string) ($input['place_district'] ?? ''));
        if ($district !== '') {
            $suffixes[] = $district;
        }

        $out = [];
        foreach ($suffixes as $suffix) {
            $candidate = $suffix === ''
                ? $base
                : $this->truncateSlug($base.'-'.$suffix);
            if ($this->isAvailable($candidate) && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
            if (count($out) >= $limit) {
                return $out;
            }
        }

        $n = 2;
        while (count($out) < $limit && $n < 100) {
            $candidate = $this->truncateSlug($base.'-'.$n);
            if ($this->isAvailable($candidate) && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
            $n++;
        }

        return $out;
    }

    public function isAvailable(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return false;
        }
        if (strlen($slug) > ChurchPlace::SLUG_MAX) {
            return false;
        }
        if (Church::where('slug', $slug)->exists()) {
            return false;
        }
        if (Schema::hasTable('organizations') && Organization::where('subdomain', $slug)->exists()) {
            return false;
        }

        return true;
    }

    public function toSlug(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        // Prefer ASCII transliteration; Arabic often needs a letter map.
        $mapped = $this->transliterateArabic($source);
        $slug = Str::slug($mapped, '-');
        if ($slug === '') {
            $slug = Str::slug(Str::ascii($mapped), '-');
        }
        if ($slug === '') {
            // Last resort: keep latin/digits only from original.
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $mapped) ?? '');
            $slug = trim($slug, '-');
        }

        return $this->truncateSlug($slug);
    }

    private function truncateSlug(string $slug): string
    {
        $slug = strtolower(trim($slug, '-'));
        if (strlen($slug) <= ChurchPlace::SLUG_MAX) {
            return $slug;
        }

        $slug = substr($slug, 0, ChurchPlace::SLUG_MAX);

        return rtrim($slug, '-');
    }

    private function transliterateArabic(string $text): string
    {
        $map = [
            'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ٱ' => 'a',
            'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h',
            'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
            'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
            'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
            'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
            'و' => 'w', 'ي' => 'y', 'ى' => 'y', 'ة' => 'a', 'ء' => '',
            'ؤ' => 'o', 'ئ' => 'i', 'لا' => 'la',
        ];

        return strtr($text, $map);
    }
}
