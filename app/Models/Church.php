<?php

namespace App\Models;

use App\Support\ChurchPlace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A church — the tenant / isolation boundary of the platform
 * (see docs/khedma-master-plan.md). T0: model + membership only; the
 * BelongsToChurch global scope arrives in T1.
 */
class Church extends Model
{
    protected $table = 'church';

    protected $primaryKey = 'church_id';

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'domain',
        'status',
        'settings',
        'permissions_version',
        'organization_id',
        'place_street',
        'place_district',
        'place_region',
        'place_governorate',
        'place_country_code',
        'place_key',
    ];

    protected $casts = ['settings' => 'array', 'permissions_version' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'church_id';
    }

    protected static function booted(): void
    {
        static::saving(function (Church $church) {
            if (($church->short_name === null || $church->short_name === '') && $church->name) {
                $church->short_name = ChurchPlace::shortName(null, $church->name);
            }
        });
    }

    /** Preferred nav / chrome label (max 40). */
    public function preferredShortName(): string
    {
        return ChurchPlace::shortName($this->short_name, $this->name);
    }

    /** Disambiguated label including place (admin lists / pickers). */
    public function shownName(): string
    {
        return ChurchPlace::shownName([
            'short_name' => $this->short_name,
            'name' => $this->name,
            'place_district' => $this->place_district,
            'place_governorate' => $this->place_governorate,
            'place_country_code' => $this->place_country_code,
        ]);
    }

    public function recomputePlaceKey(): ?string
    {
        return ChurchPlace::placeKey([
            'name' => $this->name,
            'place_country_code' => $this->place_country_code,
            'place_governorate' => $this->place_governorate,
            'place_district' => $this->place_district,
        ]);
    }

    /** Bump to invalidate all cached effective-permission entries for this church (T3-enforce). */
    public function bumpPermissionsVersion(): void
    {
        $this->increment('permissions_version');
    }

    /** P1.1 — organizations-shaped registry row (numerically aligned when provisioned). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChurchUser::class, 'church_id', 'church_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'church_user', 'church_id', 'user_id', 'church_id', 'user_id')
            ->withPivot('status', 'joined_at');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ChurchCapability::class, 'church_id', 'church_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'church_id', 'church_id');
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChurchSubscription::class, 'church_id', 'church_id');
    }

    public function entitlementSnapshot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChurchEntitlementSnapshot::class, 'church_id', 'church_id');
    }

    public function entitlementOverrides(): HasMany
    {
        return $this->hasMany(ChurchEntitlementOverride::class, 'church_id', 'church_id');
    }

    public function isSubscriptionManaged(): bool
    {
        $subscription = $this->subscription;
        if (! $subscription) {
            return false;
        }

        return $subscription->isSubscriptionManaged();
    }

    public function entitlementValue(string $featureKey): mixed
    {
        return app(\App\Billing\EntitlementResolver::class)->value($this, $featureKey);
    }

    public function hasEntitlement(string $featureKey): bool
    {
        return (bool) app(\App\Billing\EntitlementResolver::class)->booleanValue($this, $featureKey);
    }

    /** Enabled capabilities keyed by capability_key (memoized on the instance). */
    public function enabledCapabilities(): Collection
    {
        if (! $this->relationLoaded('capabilities')) {
            $this->setRelation('capabilities', $this->capabilities()->get());
        }

        return $this->capabilities->where('enabled', true)->keyBy('capability_key');
    }

    public function hasCapability(string $key): bool
    {
        // Keys outside the catalog are treated as available (fail-open) so an as-yet
        // unmodeled capability can't accidentally 404 a route.
        if (! array_key_exists($key, (array) config('capabilities'))) {
            return true;
        }

        return $this->enabledCapabilities()->has($key);
    }

    /** Catalog defaults merged with this church's overrides for the capability. */
    public function capabilityConfig(string $key): array
    {
        $defaults = (array) data_get(config('capabilities'), "{$key}.config", []);
        $capability = $this->enabledCapabilities()->get($key);
        $override = $capability ? (array) ($capability->config ?? []) : [];

        return array_replace($defaults, $override);
    }

    /** The default church that all pre-existing data was backfilled into (Tenant Zero). */
    public static function main(): self
    {
        $slug = config('tenancy.main_slug');
        $main = static::where('slug', $slug)->first();
        if ($main) {
            return $main;
        }

        // Prefer church_id=1 (Tenant Zero backfill) before failing the whole request stack.
        $fallback = static::query()->orderBy('church_id')->first();
        if ($fallback) {
            return $fallback;
        }

        return static::where('slug', $slug)->firstOrFail();
    }
}
