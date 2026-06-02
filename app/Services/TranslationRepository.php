<?php

namespace App\Services;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

class TranslationRepository
{
    public function mergeDatabaseLines(string $locale): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $lines = Cache::remember(Translation::cacheKey($locale), 3600, function () use ($locale) {
            return Translation::query()
                ->where('locale', $locale)
                ->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->pluck('value', 'key')->all())
                ->all();
        });

        foreach ($lines as $group => $groupLines) {
            if (! is_array($groupLines)) {
                continue;
            }

            $dottedLines = [];

            foreach ($groupLines as $key => $value) {
                if (is_string($value)) {
                    $dottedLines["{$group}.{$key}"] = $value;
                }
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
