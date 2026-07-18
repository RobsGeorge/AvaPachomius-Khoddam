<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use BelongsToChurch;

    public $timestamps = false;

    protected $table = 'activity_logs';

    protected $primaryKey = 'activity_log_id';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_summary',
        'http_method',
        'route_name',
        'url',
        'request_input',
        'response_status',
        'created_at',
    ];

    protected $casts = [
        'request_input'   => 'array',
        'response_status' => 'integer',
        'created_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
