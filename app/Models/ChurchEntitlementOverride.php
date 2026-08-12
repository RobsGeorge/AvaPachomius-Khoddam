<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchEntitlementOverride extends Model
{
    protected $table = 'church_entitlement_override';

    protected $primaryKey = 'church_entitlement_override_id';

    protected $fillable = [
        'church_id',
        'feature_key',
        'value',
        'expires_at',
        'reason',
        'granted_by_user_id',
    ];

    protected $casts = [
        'value' => 'array',
        'expires_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function resolvedValue(): mixed
    {
        $value = $this->value;

        return is_array($value) && array_key_exists('v', $value) ? $value['v'] : $value;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
