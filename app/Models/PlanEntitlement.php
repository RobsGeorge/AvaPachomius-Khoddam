<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntitlement extends Model
{
    protected $table = 'plan_entitlement';

    protected $primaryKey = 'plan_entitlement_id';

    public $timestamps = false;

    protected $fillable = ['plan_id', 'feature_key', 'value'];

    protected $casts = ['value' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'plan_id');
    }

    /** Unwrap JSON scalar stored as single-element or raw value. */
    public function resolvedValue(): mixed
    {
        $value = $this->value;

        return is_array($value) && array_key_exists('v', $value) ? $value['v'] : $value;
    }

    public static function wrapValue(mixed $value): array
    {
        return ['v' => $value];
    }
}
