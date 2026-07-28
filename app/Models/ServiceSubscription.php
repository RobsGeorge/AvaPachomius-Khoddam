<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSubscription extends Model
{
    protected $table = 'service_subscription';

    protected $primaryKey = 'service_subscription_id';

    protected $fillable = [
        'service_id',
        'church_id',
        'plan_id',
        'plan_price_id',
        'billing_account_id',
        'status',
        'stripe_subscription_id',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'comped_by_user_id',
        'comp_reason',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

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

    public function paysIndependently(): bool
    {
        if (! $this->billing_account_id) {
            return false;
        }

        $account = $this->relationLoaded('billingAccount')
            ? $this->billingAccount
            : $this->billingAccount()->first();

        return $account && (int) $account->service_id === (int) $this->service_id;
    }
}
