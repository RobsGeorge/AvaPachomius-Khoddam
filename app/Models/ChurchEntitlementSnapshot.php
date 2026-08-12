<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchEntitlementSnapshot extends Model
{
    protected $table = 'church_entitlement_snapshot';

    protected $primaryKey = 'church_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['church_id', 'features', 'resolved_at'];

    protected $casts = [
        'features' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
