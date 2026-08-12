<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchSubscription extends Model
{
    protected $table = 'church_subscription';

    protected $primaryKey = 'church_subscription_id';

    protected $fillable = [
        'church_id',
        'plan_id',
        'plan_price_id',
        'billing_account_id',
        'status',
        'stripe_subscription_id',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'seat_count_purchased',
        'seat_count_effective',
        'comped_by_user_id',
        'comp_reason',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'seat_count_purchased' => 'integer',
        'seat_count_effective' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id', 'plan_price_id');
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id', 'billing_account_id');
    }

    public function grantsAccess(): bool
    {
        return in_array($this->status, (array) config('billing.subscription_active_statuses'), true);
    }

    public function isSubscriptionManaged(): bool
    {
        return $this->grantsAccess() && $this->plan_id !== null;
    }
}
