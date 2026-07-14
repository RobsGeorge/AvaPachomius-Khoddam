<?php

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F-13 — key-level ar/en parity guard. The file-level guard (TenantIsolationTest)
 * ensures every en file has an ar counterpart; this extends it to the key level so
 * no *new* string ships untranslated.
 *
 * A codebase-wide sweep would fail on pre-existing debt (currently a feedback/survey
 * section in pages.php), so the known gaps are captured in
 * `locale-parity-baseline.txt`. This test fails only on keys added without a
 * translation — i.e. it blocks NEW drift while the baseline is burned down over time.
 */
class LocaleKeyParityTest extends TestCase
{
    public function test_no_new_untranslated_keys_are_introduced(): void
    {
        $baseline = $this->baseline();
        $live = $this->liveMismatches();

        $newGaps = array_values(array_diff($live, $baseline));
        sort($newGaps);

        $this->assertSame(
            [],
            $newGaps,
            "New untranslated keys detected — add the Arabic/English counterpart (do NOT add to the baseline):\n"
                .implode("\n", $newGaps)
        );
    }

    public function test_baseline_has_no_stale_entries(): void
    {
        // Encourage burn-down: if a baselined gap has been fixed, remove it from the file.
        $stale = array_values(array_diff($this->baseline(), $this->liveMismatches()));
        sort($stale);

        $this->assertSame(
            [],
            $stale,
            "These baseline entries are now translated — delete them from locale-parity-baseline.txt:\n"
                .implode("\n", $stale)
        );
    }

    /** @return list<string> */
    private function liveMismatches(): array
    {
        $mismatches = [];

        foreach (File::files(lang_path('en')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $name = $file->getFilename();
            $arPath = lang_path('ar/'.$name);
            if (! File::exists($arPath)) {
                continue; // file-level parity is covered elsewhere
            }

            $enKeys = $this->flatten(require $file->getPathname());
            $arKeys = $this->flatten(require $arPath);

            foreach (array_diff($enKeys, $arKeys) as $missing) {
                $mismatches[] = "ar/{$name} missing key: {$missing}";
            }
            foreach (array_diff($arKeys, $enKeys) as $extra) {
                $mismatches[] = "en/{$name} missing key: {$extra}";
            }
        }

        return $mismatches;
    }

    /** @return list<string> */
    private function baseline(): array
    {
        $path = __DIR__.'/locale-parity-baseline.txt';
        if (! File::exists($path)) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) File::get($path)))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>  $array
     * @return list<string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
