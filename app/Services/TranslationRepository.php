<?php

namespace App\Services;

use App\Models\Translation;
use App\Support\ResilientCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

class TranslationRepository
{
    public function mergeDatabaseLines(string $locale): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $lines = ResilientCache::remember(Translation::cacheKey($locale), 3600, function () use ($locale) {
            return Translation::query()
                ->where('locale', $locale)
                ->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->pluck('value', 'key')->all())
                ->all();
        });

        /** @var \Illuminate\Translation\Translator $translator */
        $translator = Lang::getFacadeRoot();

        foreach ($lines as $group => $groupLines) {
            if (! is_array($groupLines) || $groupLines === []) {
                continue;
            }

            // addLines() marks the group as loaded. Without loading the file first,
            // keys that have no DB row never resolve and __() returns the raw key.
            $translator->load('*', (string) $group, $locale);

            $dottedLines = [];

            foreach ($groupLines as $key => $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                // Skip overrides saved when the runtime lookup failed (value = "group.key").
                if ($value === "{$group}.{$key}") {
                    continue;
                }

                $dottedLines["{$group}.{$key}"] = $value;
            }

            if ($dottedLines !== []) {
                Lang::addLines($dottedLines, $locale, '*');
            }
        }
    }

    public function flushCache(?string $locale = null): void
    {
        foreach (['ar', 'en'] as $code) {
            if ($locale === null || $locale === $code) {
                Cache::forget(Translation::cacheKey($code));
            }
        }
    }

    private function tableExists(): bool
    {
        try {
            return \Schema::hasTable('translations');
        } catch (\Throwable) {
            return false;
        }
    }
}
