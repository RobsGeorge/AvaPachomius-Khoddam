<?php

namespace App\Billing;

use App\Models\Church;
use App\Models\ChurchEntitlementOverride;
use App\Models\ChurchEntitlementSnapshot;
use App\Models\ChurchSubscription;
use App\Models\PlanEntitlement;
use App\Models\PlatformFeature;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves effective entitlements for a church: plan → overrides → snapshot cache.
 */
class EntitlementResolver
{
    public function __construct(
        private PlatformFeatureCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(Church $church, bool $force = false): array
    {
        if (! $force && $church->relationLoaded('entitlementSnapshot') && $church->entitlementSnapshot) {
            return (array) $church->entitlementSnapshot->features;
        }

        if (! $force) {
            $cached = ChurchEntitlementSnapshot::find($church->church_id);
            if ($cached) {
                return (array) $cached->features;
            }
        }

        return $this->computeAndPersist($church);
    }

    public function value(Church $church, string $featureKey): mixed
    {
        $features = $this->resolve($church);

        if (array_key_exists($featureKey, $features)) {
            return $features[$featureKey];
        }

        return $this->defaultFor($featureKey);
    }

    public function booleanValue(Church $church, string $featureKey): bool
    {
        return (bool) $this->value($church, $featureKey);
    }

    public function limitValue(Church $church, string $featureKey): ?int
    {
        $value = $this->value($church, $featureKey);
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    public function computeAndPersist(Church $church): array
    {
        $features = $this->compute($church);

        if (Schema::hasTable('church_entitlement_snapshot')) {
            ChurchEntitlementSnapshot::updateOrCreate(
                ['church_id' => $church->church_id],
                ['features' => $features, 'resolved_at' => now()]
            );
        }

        $church->unsetRelation('entitlementSnapshot');
        $church->setRelation('entitlementSnapshot', new ChurchEntitlementSnapshot([
            'church_id' => $church->church_id,
            'features' => $features,
            'resolved_at' => now(),
        ]));

        return $features;
    }

    /** @return array<string, mixed> */
    private function compute(Church $church): array
    {
        $features = [];

        foreach ($this->catalog->activeKeys() as $key) {
            $features[$key] = $this->defaultFor($key);
        }

        $subscription = $this->subscriptionFor($church);
        if ($subscription && $subscription->grantsAccess() && $subscription->plan_id) {
            $planEntitlements = PlanEntitlement::query()
                ->where('plan_id', $subscription->plan_id)
                ->get();

            foreach ($planEntitlements as $entitlement) {
                $features[$entitlement->feature_key] = $entitlement->resolvedValue();
            }

            if ($subscription->seat_count_purchased !== null) {
                $features['max_active_users'] = $subscription->seat_count_purchased;
            } elseif ($subscription->plan) {
                $features['max_active_users'] = $subscription->plan->includes_seats;
            }
        }

        if (Schema::hasTable('church_entitlement_override')) {
            $overrides = ChurchEntitlementOverride::query()
                ->where('church_id', $church->church_id)
                ->get()
                ->filter(fn (ChurchEntitlementOverride $o) => ! $o->isExpired());

            foreach ($overrides as $override) {
                $features[$override->feature_key] = $override->resolvedValue();
            }
        }

        return $features;
    }

    private function subscriptionFor(Church $church): ?ChurchSubscription
    {
        if ($church->relationLoaded('subscription')) {
            return $church->subscription;
        }

        if (! Schema::hasTable('church_subscription')) {
            return null;
        }

        return ChurchSubscription::query()->where('church_id', $church->church_id)->first();
    }

    private function defaultFor(string $featureKey): mixed
    {
        $configDef = $this->catalog->definition($featureKey);
        if ($configDef && array_key_exists('default', $configDef)) {
            return $configDef['default'];
        }

        if (Schema::hasTable('platform_feature')) {
            $feature = PlatformFeature::find($featureKey);
            if ($feature && $feature->type === 'boolean') {
                return false;
            }
        }

        return null;
    }
}
