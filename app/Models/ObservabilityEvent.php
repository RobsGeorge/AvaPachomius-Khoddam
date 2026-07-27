<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservabilityEvent extends Model
{
    use BelongsToChurch;

    protected $table = 'observability_events';

    protected $fillable = [
        'occurred_at',
        'severity',
        'category',
        'fingerprint',
        'message',
        'exception_class',
        'stack_excerpt',
        'http_status',
        'url',
        'method',
        'route_name',
        'user_id',
        'church_id',
        'service_id',
        'session_id',
        'request_id',
        'context',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'http_status' => 'integer',
        'context' => 'array',
        'user_id' => 'integer',
        'church_id' => 'integer',
        'service_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
