<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveFeedbackSession extends Model
{
    public const STATUS_LIVE = 'live';
    public const STATUS_CLOSED = 'closed';

    protected $primaryKey = 'session_id';

    protected $fillable = [
        'course_id', 'module_id', 'host_user_id', 'status', 'mandatory_gate',
        'live_mode', 'current_step', 'started_at', 'closed_at',
    ];

    protected $casts = [
        'mandatory_gate' => 'boolean',
        'live_mode' => 'boolean',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id', 'user_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(LiveFeedbackResponse::class, 'session_id', 'session_id');
    }

    public function channelName(): string
    {
        return 'live-feedback.'.$this->session_id;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function getRouteKeyName(): string
    {
        return 'session_id';
    }
}
