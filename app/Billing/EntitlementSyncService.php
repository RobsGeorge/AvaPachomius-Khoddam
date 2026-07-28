<?php

namespace App\Billing;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\PlatformFeature;
use App\Services\AuditLogService;
use App\Services\CoursePermissionResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Recomputes entitlements and syncs boolean capability-mapped features to church_capability.
 */
class EntitlementSyncService
{
    public function __construct(
        private EntitlementResolver $resolver,
    ) {}

    public function sync(Church $church): array
    {
        $features = $this->resolver->computeAndPersist($church->fresh());

        if (Schema::hasTable('platform_feature') && Schema::hasTable('church_capability')) {
            $this->syncCapabilities($church, $features);
        }

        AuditLogService::recordEvent('billing.entitlements_synced', [
            'church_id' => $church->church_id,
            'feature_count' => count($features),
        ]);

        return $features;
    }

    /** @param  array<string, mixed>  $features */
    private function syncCapabilities(Church $church, array $features): void
    {
        if (! $this->isSubscriptionManaged($church)) {
            return;
        }

        $mapped = PlatformFeature::query()
            ->whereNotNull('maps_to_capability')
            ->get();

        $catalog = array_keys((array) config('capabilities'));
        $changed = false;

        foreach ($catalog as $capabilityKey) {
            $enabled = $this->capabilityEnabledFromFeatures($capabilityKey, $features, $mapped);
            $row = ChurchCapability::firstOrNew([
                'church_id' => $church->church_id,
                'capability_key' => $capabilityKey,
            ]);

            if ((bool) $row->enabled !== $enabled) {
                $changed = true;
            }

            $row->enabled = $enabled;
            if (! $row->exists) {
                $row->config = null;
            }
            $row->save();
        }

        if ($changed) {
            app(CoursePermissionResolver::class)->bumpChurchPermissionsVersion($church->fresh());
        }

        $church->unsetRelation('capabilities');
    }

    private function isSubscriptionManaged(Church $church): bool
    {
        $subscription = $church->subscription;
        if (! $subscription) {
            return false;
        }

        return $subscription->isSubscriptionManaged();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PlatformFeature>  $mapped
     * @param  array<string, mixed>  $features
     */
    private function capabilityEnabledFromFeatures(string $capabilityKey, array $features, $mapped): bool
    {
        $feature = $mapped->firstWhere('maps_to_capability', $capabilityKey);
        if (! $feature) {
            return (bool) ($features[$capabilityKey] ?? false);
        }

        return (bool) ($features[$feature->feature_key] ?? false);
    }
}
