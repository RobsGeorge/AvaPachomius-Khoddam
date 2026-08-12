<?php

namespace App\Billing;

use App\Models\Church;
use App\Models\ChurchEntitlementOverride;
use App\Models\ChurchEntitlementSnapshot;
use App\Models\ChurchService;
use App\Models\ChurchSubscription;
use App\Models\PlanEntitlement;
use App\Models\PlatformFeature;
use App\Models\ServiceEntitlementOverride;
use App\Models\ServiceEntitlementSnapshot;
use App\Models\ServiceSubscription;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves effective entitlements for a church (floor) and for a service
 * (church floor + service add-ons via EntitlementMerger).
 */
class EntitlementResolver
{
    public function __construct(
        private PlatformFeatureCatalog $catalog,
        private EntitlementMerger $merger,
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

    /**
     * Effective entitlements for a service context: church floor merged with service add-ons.
     *
     * @return array<string, mixed>
     */
    public function resolveForService(ChurchService $service, bool $force = false): array
    {
        $church = $service->relationLoaded('church')
            ? $service->church
            : Church::query()->find($service->church_id);

        $floor = $church ? $this->resolve($church, $force) : $this->defaultsMap();
        $addons = $this->computeServiceAddons($service);

        $merged = $this->merger->merge($floor, $addons);

        if (Schema::hasTable('service_entitlement_snapshot')) {
            ServiceEntitlementSnapshot::updateOrCreate(
                ['service_id' => $service->service_id],
                ['features' => $merged, 'resolved_at' => now()]
            );
        }

        return $merged;
    }

    /** Service add-on features only (plan + overrides), not merged with church. */
    public function computeServiceAddons(ChurchService $service): array
    {
        $features = [];

        foreach ($this->catalog->activeKeys() as $key) {
            // Neutral base so merge only *adds* when service sets a richer value.
            $def = $this->catalog->definition($key);
            $type = $def['type'] ?? 'boolean';
            // Neutral bases that never beat a church floor value on merge.
            $features[$key] = match ($type) {
                'boolean' => false,
                'limit' => 0,
                'enum' => ($def['enum_options'][0] ?? 'none'),
                default => null,
            };
        }

        $subscription = $this->serviceSubscriptionFor($service);
        if ($subscription && $subscription->grantsAccess() && $subscription->plan_id) {
            $planEntitlements = PlanEntitlement::query()
                ->where('plan_id', $subscription->plan_id)
                ->get();

            foreach ($planEntitlements as $entitlement) {
                $features[$entitlement->feature_key] = $entitlement->resolvedValue();
            }
        }

        if (Schema::hasTable('service_entitlement_override')) {
            $overrides = ServiceEntitlementOverride::query()
                ->where('service_id', $service->service_id)
                ->get()
                ->filter(fn (ServiceEntitlementOverride $o) => ! $o->isExpired());

            foreach ($overrides as $override) {
                $features[$override->feature_key] = $override->resolvedValue();
            }
        }

        return $features;
    }

    public function value(Church $church, string $featureKey): mixed
    {
        $features = $this->resolve($church);

        if (array_key_exists($featureKey, $features)) {
            return $features[$featureKey];
        }

        return $this->defaultFor($featureKey);
    }

    public function valueForService(ChurchService $service, string $featureKey): mixed
    {
        $features = $this->resolveForService($service);

        if (array_key_exists($featureKey, $features)) {
            return $features[$featureKey];
        }

        return $this->defaultFor($featureKey);
    }

    public function booleanValue(Church $church, string $featureKey): bool
    {
        return (bool) $this->value($church, $featureKey);
    }

    public function booleanValueForService(ChurchService $service, string $featureKey): bool
    {
        return (bool) $this->valueForService($service, $featureKey);
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
        $features = $this->defaultsMap();

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

    /** @return array<string, mixed> */
    private function defaultsMap(): array
    {
        $features = [];
        foreach ($this->catalog->activeKeys() as $key) {
            $features[$key] = $this->defaultFor($key);
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

    private function serviceSubscriptionFor(ChurchService $service): ?ServiceSubscription
    {
        if ($service->relationLoaded('subscription')) {
            return $service->subscription;
        }

        if (! Schema::hasTable('service_subscription')) {
            return null;
        }

        return ServiceSubscription::query()->where('service_id', $service->service_id)->first();
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
