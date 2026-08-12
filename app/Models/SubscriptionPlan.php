<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plan';

    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'tier_rank',
        'is_public',
        'is_custom',
        'status',
        'scope',
        'includes_seats',
        'seat_overage_policy',
        'stripe_product_id',
        'metadata',
    ];

    protected $casts = [
        'tier_rank' => 'integer',
        'is_public' => 'boolean',
        'is_custom' => 'boolean',
        'includes_seats' => 'integer',
        'metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'plan_id';
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class, 'plan_id', 'plan_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class, 'plan_id', 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ChurchSubscription::class, 'plan_id', 'plan_id');
    }

    public function serviceSubscriptions(): HasMany
    {
        return $this->hasMany(ServiceSubscription::class, 'plan_id', 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function allowsChurch(): bool
    {
        return in_array($this->scope ?? 'both', ['church', 'both'], true);
    }

    public function allowsService(): bool
    {
        return in_array($this->scope ?? 'both', ['service', 'both'], true);
    }
}
