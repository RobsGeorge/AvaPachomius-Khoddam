<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEntitlementSnapshot extends Model
{
    protected $table = 'service_entitlement_snapshot';

    protected $primaryKey = 'service_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['service_id', 'features', 'resolved_at'];

    protected $casts = [
        'features' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }
}
