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
            Lang::addLines($groupLines, $locale, $group);
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
