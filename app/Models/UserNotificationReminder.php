<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationReminder extends Model
{
    public const RECURRENCE_ONCE = 'once';

    public const RECURRENCE_DAILY = 'daily';

    public const RECURRENCE_WEEKLY = 'weekly';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'remind_at',
        'recurrence',
        'channels',
        'last_fired_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'last_fired_at' => 'datetime',
        'channels' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
