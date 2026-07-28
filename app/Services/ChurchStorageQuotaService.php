<?php

namespace App\Services;

use App\Models\Church;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;

class ChurchStorageQuotaService
{
    public function quotaBytes(?Church $church): int
    {
        if (! $church) {
            return (int) config('curriculum.default_quota_bytes');
        }

        $settings = $church->settings ?? [];
        $key = (string) config('curriculum.settings_quota_key');

        return (int) ($settings[$key] ?? config('curriculum.default_quota_bytes'));
    }

    public function usedBytes(?Church $church): int
    {
        if (! $church) {
            return 0;
        }

        $settings = $church->settings ?? [];
        $key = (string) config('curriculum.settings_used_key');

        if (array_key_exists($key, $settings)) {
            return max(0, (int) $settings[$key]);
        }

        return $this->reconcileUsedBytes($church);
    }

    public function remainingBytes(?Church $church): int
    {
        return max(0, $this->quotaBytes($church) - $this->usedBytes($church));
    }

    public function usagePercent(?Church $church): float
    {
        $quota = $this->quotaBytes($church);
        if ($quota <= 0) {
            return 100.0;
        }

        return min(100.0, round(($this->usedBytes($church) / $quota) * 100, 1));
    }

    public function assertCanStore(?Church $church, int $additionalBytes): void
    {
        if ($additionalBytes <= 0) {
            return;
        }

        $remaining = $this->remainingBytes($church);
        if ($additionalBytes > $remaining) {
            throw new \RuntimeException(__('curriculum.storage_quota_exceeded', [
                'remaining' => StorageFormat::bytes($remaining),
                'quota' => StorageFormat::bytes($this->quotaBytes($church)),
            ]));
        }
    }

    public function incrementUsed(?Church $church, int $bytes): void
    {
        if (! $church || $bytes <= 0) {
            return;
        }

        $this->adjustUsed($church, $bytes);
    }

    public function decrementUsed(?Church $church, int $bytes): void
    {
        if (! $church || $bytes <= 0) {
            return;
        }

        $this->adjustUsed($church, -$bytes);
    }

    public function reconcileUsedBytes(Church $church): int
    {
        $sum = (int) MediaAsset::query()
            ->where('church_id', $church->church_id)
            ->where('context', MediaAsset::CONTEXT_CURRICULUM)
            ->sum('size_bytes');

        $this->setUsedBytes($church, $sum);

        return $sum;
    }

    public function setQuotaBytes(Church $church, int $bytes): void
    {
        $settings = $church->settings ?? [];
        $settings[(string) config('curriculum.settings_quota_key')] = max(0, $bytes);
        $church->update(['settings' => $settings]);
    }

    private function adjustUsed(Church $church, int $delta): void
    {
        DB::transaction(function () use ($church, $delta) {
            $locked = Church::query()->whereKey($church->church_id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $settings = $locked->settings ?? [];
            $key = (string) config('curriculum.settings_used_key');
            $current = (int) ($settings[$key] ?? $this->reconcileUsedBytes($locked));
            $settings[$key] = max(0, $current + $delta);
            $locked->update(['settings' => $settings]);
            $church->settings = $settings;
        });
    }

    private function setUsedBytes(Church $church, int $bytes): void
    {
        $settings = $church->settings ?? [];
        $settings[(string) config('curriculum.settings_used_key')] = max(0, $bytes);
        $church->update(['settings' => $settings]);
    }
}
