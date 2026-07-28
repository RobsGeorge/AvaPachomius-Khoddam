<?php

namespace App\Billing;

use App\Models\PlatformFeature;
use Illuminate\Support\Facades\Schema;

/**
 * Syncs config/platform_entitlements.php into the platform_feature table.
 */
class PlatformFeatureCatalog
{
    public function syncFromConfig(): int
    {
        if (! Schema::hasTable('platform_feature')) {
            return 0;
        }

        $count = 0;
        foreach ((array) config('platform_entitlements') as $key => $def) {
            PlatformFeature::updateOrCreate(
                ['feature_key' => $key],
                [
                    'type' => $def['type'],
                    'maps_to_capability' => $def['maps_to_capability'] ?? null,
                    'label_key' => $def['label'],
                    'enum_options' => $def['enum_options'] ?? null,
                    'sort_order' => (int) ($def['sort_order'] ?? 0),
                    'is_active' => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function definition(string $featureKey): ?array
    {
        return config("platform_entitlements.{$featureKey}");
    }

    public function activeKeys(): array
    {
        if (! Schema::hasTable('platform_feature')) {
            return array_keys((array) config('platform_entitlements'));
        }

        return PlatformFeature::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('feature_key')
            ->all();
    }
}
