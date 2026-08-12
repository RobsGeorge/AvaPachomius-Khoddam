<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class UsageRollup extends Model
{
    use BelongsToChurch;

    protected $table = 'usage_rollups';

    protected $fillable = [
        'bucket_start',
        'church_id',
        'service_id',
        'active_users',
        'request_count',
        'unique_sessions',
    ];

    protected $casts = [
        'bucket_start' => 'datetime',
        'church_id' => 'integer',
        'service_id' => 'integer',
        'active_users' => 'integer',
        'request_count' => 'integer',
        'unique_sessions' => 'integer',
    ];
}
