<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformFeature extends Model
{
    protected $table = 'platform_feature';

    protected $primaryKey = 'feature_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'feature_key',
        'type',
        'maps_to_capability',
        'label_key',
        'enum_options',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'enum_options' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
