<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    protected $table = 'plan_price';

    protected $primaryKey = 'plan_price_id';

    protected $fillable = [
        'plan_id',
        'billing_interval',
        'amount_minor',
        'currency',
        'stripe_price_id',
        'trial_days',
        'is_default',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'trial_days' => 'integer',
        'is_default' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }
}
