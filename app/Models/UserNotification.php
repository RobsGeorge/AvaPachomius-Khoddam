<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const TYPE_ADMIN_ANNOUNCEMENT = 'admin_announcement';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'source_type',
        'source_id',
        'priority',
        'read_at',
        'dismissed_at',
        'metadata',
        'dedupe_key',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function isAnnouncement(): bool
    {
        return $this->type === self::TYPE_ADMIN_ANNOUNCEMENT;
    }
}
