<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAccount extends Model
{
    protected $table = 'billing_account';

    protected $primaryKey = 'billing_account_id';

    protected $fillable = [
        'organization_id',
        'service_id',
        'stripe_customer_id',
        'billing_email',
        'tax_id',
        'default_currency',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ChurchSubscription::class, 'billing_account_id', 'billing_account_id');
    }

    public function serviceSubscriptions(): HasMany
    {
        return $this->hasMany(ServiceSubscription::class, 'billing_account_id', 'billing_account_id');
    }

    public function isServiceOwned(): bool
    {
        return $this->service_id !== null;
    }
}
