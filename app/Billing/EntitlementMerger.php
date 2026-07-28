<?php

namespace App\Billing;

/**
 * Merges church floor entitlements with service add-ons (2C):
 * boolean OR, limit max (null = unlimited), enum by richer rank.
 */
class EntitlementMerger
{
    /**
     * @param  array<string, mixed>  $churchFeatures
     * @param  array<string, mixed>  $serviceFeatures
     * @return array<string, mixed>
     */
    public function merge(array $churchFeatures, array $serviceFeatures): array
    {
        $keys = array_unique(array_merge(array_keys($churchFeatures), array_keys($serviceFeatures)));
        $merged = [];

        foreach ($keys as $key) {
            $hasChurch = array_key_exists($key, $churchFeatures);
            $hasService = array_key_exists($key, $serviceFeatures);

            if ($hasChurch && ! $hasService) {
                $merged[$key] = $churchFeatures[$key];
                continue;
            }
            if ($hasService && ! $hasChurch) {
                $merged[$key] = $serviceFeatures[$key];
                continue;
            }

            $merged[$key] = $this->mergeValue($key, $churchFeatures[$key], $serviceFeatures[$key]);
        }

        return $merged;
    }

    public function mergeValue(string $featureKey, mixed $churchValue, mixed $serviceValue): mixed
    {
        $type = (string) data_get(config("platform_entitlements.{$featureKey}"), 'type', 'boolean');

        return match ($type) {
            'boolean' => (bool) $churchValue || (bool) $serviceValue,
            'limit' => $this->mergeLimit($churchValue, $serviceValue),
            'enum' => $this->mergeEnum($featureKey, $churchValue, $serviceValue),
            default => $serviceValue !== null ? $serviceValue : $churchValue,
        };
    }

    private function mergeLimit(mixed $churchValue, mixed $serviceValue): ?int
    {
        if ($churchValue === null || $serviceValue === null) {
            // Either side unlimited → unlimited (unless both are concrete and we take max).
            if ($churchValue === null && $serviceValue === null) {
                return null;
            }
            if ($churchValue === null) {
                return null;
            }
            if ($serviceValue === null) {
                return null;
            }
        }

        return max((int) $churchValue, (int) $serviceValue);
    }

    private function mergeEnum(string $featureKey, mixed $churchValue, mixed $serviceValue): mixed
    {
        if ($featureKey === 'mobile_app') {
            $ranks = (array) config('billing.mobile_app_ranks', []);
            $churchRank = (int) ($ranks[(string) $churchValue] ?? 0);
            $serviceRank = (int) ($ranks[(string) $serviceValue] ?? 0);

            return $serviceRank >= $churchRank ? $serviceValue : $churchValue;
        }

        return $serviceValue !== null ? $serviceValue : $churchValue;
    }
}
